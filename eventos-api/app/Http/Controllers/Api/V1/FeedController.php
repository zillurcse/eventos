<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\NewFeedPost;
use App\Http\Controllers\Controller;
use App\Http\Resources\FeedPostResource;
use App\Models\Event;
use App\Models\FeedComment;
use App\Models\FeedPost;
use App\Models\FeedReaction;
use App\Models\Participation;
use App\Services\Gamification\GamificationScorer;
use App\Support\CommunicationCapabilities;
use App\Support\FeedMentions;
use App\Support\GamificationActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * Event feed (architecture §6.6). Acts as the resolved participation
 * (ResolveParticipant middleware). New posts fan out over Reverb.
 */
class FeedController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $pid = $request->attributes->get('participation_id');

        $data = $request->validate([
            'type' => ['nullable', 'in:all,image,video,pdf,poll,looking_for,offering'],
            'mine' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $query = FeedPost::where('event_id', $request->attributes->get('event_id'))
            // Constrain the eager load to the current participant so each post
            // carries whether *this* viewer reacted (one extra query).
            ->with(['reactions' => fn ($q) => $q->where('participation_id', $pid)]);

        // The wall shows only published posts; "My Posts" shows the author
        // their own posts in every moderation state (pending / published /
        // rejected) so they can track approval.
        if (! $request->boolean('mine')) {
            $query->where('status', 'published');
        }

        // "Filter By" type — keyed on the post's meta.type. Legacy rows with no
        // meta are treated as plain text (only surfaced under "All").
        if (! empty($data['type']) && $data['type'] !== 'all') {
            $query->where('meta->type', $data['type']);
        }

        // "My Posts".
        if ($request->boolean('mine')) {
            $query->where('author_type', 'participation')->where('author_id', $pid);
        }

        // Search across post body and networking tags.
        if (! empty($data['q'])) {
            $term = '%'.$data['q'].'%';
            $query->where(function ($w) use ($term) {
                $w->where('body', 'ilike', $term)
                    ->orWhereRaw(
                        "EXISTS (SELECT 1 FROM jsonb_array_elements_text(COALESCE(meta->'tags', '[]'::jsonb)) tag WHERE tag ILIKE ?)",
                        [$term],
                    );
            });
        }

        $posts = $query->orderByDesc('is_pinned')->latest('id')->paginate(20)->withQueryString();
        FeedPost::warmAuthorCache($posts->getCollection());

        $collection = FeedPostResource::collection($posts);
        $event = Event::with('settings')->find($request->attributes->get('event_id'));
        $participation = Participation::find($request->attributes->get('participation_id'));
        if ($event && $participation) {
            $collection->additional([
                'communication' => CommunicationCapabilities::forParticipant(
                    $participation,
                    CommunicationCapabilities::communication($event),
                ),
            ]);
        }

        return $collection;
    }

    /**
     * The distinct networking tags in circulation on this event's feed, split by
     * kind. Feeds the Profile › Looking & Offering pickers so an attendee chooses
     * from what people are actually looking for / offering rather than typing into
     * the void. Published posts only — pending/rejected tags aren't public yet.
     */
    public function networkingTags(Request $request): JsonResponse
    {
        $rows = FeedPost::where('event_id', $request->attributes->get('event_id'))
            ->where('status', 'published')
            ->whereIn('meta->type', ['looking_for', 'offering'])
            ->get(['meta']);

        $buckets = ['looking_for' => [], 'offering' => []];
        foreach ($rows as $row) {
            $type = data_get($row->meta, 'type');
            if (! isset($buckets[$type])) {
                continue;
            }
            foreach ((array) data_get($row->meta, 'tags', []) as $tag) {
                $tag = trim((string) $tag);
                if ($tag !== '') {
                    // Keyed by a lower-cased form so "Banker" and "banker" collapse;
                    // the first-seen casing is what we surface.
                    $buckets[$type][mb_strtolower($tag)] ??= $tag;
                }
            }
        }

        foreach ($buckets as &$bucket) {
            $bucket = array_values($bucket);
            natcasesort($bucket);
            $bucket = array_values($bucket);
        }
        unset($bucket);

        return response()->json(['data' => $buckets]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'visibility' => ['nullable', 'in:public,attendees,group'],
            'type' => ['nullable', 'in:text,image,video,pdf,poll,looking_for,offering'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*.kind' => ['required_with:attachments', 'in:image,video,pdf'],
            'attachments.*.url' => ['required_with:attachments', 'url', 'max:2000'],
            'attachments.*.name' => ['nullable', 'string', 'max:255'],
            'poll.options' => ['required_if:type,poll', 'array', 'min:2', 'max:8'],
            'poll.options.*' => ['string', 'max:200'],
            'poll.allow_multiple' => ['nullable', 'boolean'],
            'tags' => ['nullable', 'array', 'max:12'],
            'tags.*' => ['string', 'max:40'],
            'mentions' => ['nullable', 'array', 'max:20'],
            'mentions.*' => ['uuid'],
        ]);

        $type = $data['type'] ?? 'text';
        $meta = $this->buildMeta($type, $data, (int) $request->attributes->get('event_id'));

        // A post must carry *something* — body text, an attachment, or a poll.
        if (trim((string) ($data['body'] ?? '')) === '' && empty($meta['attachments']) && $type !== 'poll') {
            throw ValidationException::withMessages(['body' => 'Write something or attach media.']);
        }

        $eventId = $request->attributes->get('event_id');
        $event = Event::with('settings')->findOrFail($eventId);
        $participation = Participation::findOrFail($request->attributes->get('participation_id'));
        $communication = CommunicationCapabilities::communication($event);

        CommunicationCapabilities::abortUnless(
            $participation,
            $communication,
            CommunicationCapabilities::feedTypeOp($type),
            'You are not allowed to create this type of post.',
        );

        // Engagement › Manage Activity Feed: on → pending, off → auto-published.
        $moderated = CommunicationCapabilities::feedPostNeedsModeration($event, $type, $communication);

        $post = FeedPost::create([
            'event_id' => $eventId,
            'author_type' => 'participation',
            'author_id' => $participation->id,
            'body' => $data['body'] ?? '',
            'visibility' => $data['visibility'] ?? 'attendees',
            'status' => $moderated ? 'pending' : 'published',
            'meta' => $meta,
        ]);

        if (! $moderated) {
            broadcast(new NewFeedPost($post));
            FeedMentions::notify(
                (int) $eventId,
                (int) $participation->id,
                (array) ($meta['mentions'] ?? []),
                'mentioned you in a post',
            );
        }

        // Points only when the post is live; pending posts award on approval.
        if (! $moderated) {
            app(GamificationScorer::class)->queue(
                (int) $participation->organization_id,
                (int) $eventId,
                (int) $participation->id,
                GamificationActions::feedPostAction($type),
                'feed_post',
                (int) $post->id,
            );
        }

        return response()->json(['data' => new FeedPostResource($post)], 201);
    }

    /** Assemble the type-specific `meta` payload for a new post. */
    protected function buildMeta(string $type, array $data, int $eventId): array
    {
        $meta = ['type' => $type];

        if (! empty($data['attachments'])) {
            $meta['attachments'] = array_map(fn ($a) => [
                'kind' => $a['kind'],
                'url' => $a['url'],
                'name' => $a['name'] ?? null,
            ], $data['attachments']);
        }

        if ($type === 'poll') {
            $options = [];
            foreach (array_values($data['poll']['options']) as $i => $text) {
                $text = trim((string) $text);
                if ($text === '') {
                    continue;
                }
                $options[] = ['id' => 'o'.($i + 1), 'text' => $text, 'votes' => 0];
            }
            $meta['poll'] = [
                'options' => $options,
                'allow_multiple' => (bool) ($data['poll']['allow_multiple'] ?? false),
                'total_votes' => 0,
                'voters' => (object) [], // participation_id → [optionId, …]
            ];
        }

        if (! empty($data['tags'])) {
            $meta['tags'] = array_values(array_unique(array_map('trim', $data['tags'])));
        }

        $mentions = FeedMentions::resolve($eventId, $data['mentions'] ?? []);
        if ($mentions !== []) {
            $meta['mentions'] = $mentions;
        }

        return $meta;
    }

    /**
     * Cast (or retract) the current participant's vote on a poll post. Honors
     * single- vs multi-choice polls and keeps per-option + total counts in step.
     */
    public function votePoll(string $event, string $post, Request $request): JsonResponse
    {
        $this->abortUnlessOp($request, 'vote_feed_polls', 'You are not allowed to vote on feed polls.');

        $pid = (string) $request->attributes->get('participation_id');
        $feedPost = $this->resolvePost($request, $post);

        $data = $request->validate(['option_id' => ['required', 'string']]);

        $meta = $feedPost->meta ?? [];
        $poll = $meta['poll'] ?? null;
        abort_unless($poll, 422, 'This post is not a poll.');

        $optionIds = array_column($poll['options'], 'id');
        abort_unless(in_array($data['option_id'], $optionIds, true), 422, 'Unknown option.');

        $voters = (array) ($poll['voters'] ?? []);
        $mine = (array) ($voters[$pid] ?? []);
        $hadVote = $mine !== [];
        $allowMultiple = (bool) ($poll['allow_multiple'] ?? false);

        if (in_array($data['option_id'], $mine, true)) {
            // Toggle the vote off.
            $mine = array_values(array_diff($mine, [$data['option_id']]));
        } elseif ($allowMultiple) {
            $mine[] = $data['option_id'];
        } else {
            $mine = [$data['option_id']]; // single-choice: replace prior vote
        }

        $voters[$pid] = array_values($mine);
        if (empty($voters[$pid])) {
            unset($voters[$pid]);
        }

        // Recount from the authoritative voter map (avoids counter drift).
        $counts = array_fill_keys($optionIds, 0);
        foreach ($voters as $picks) {
            foreach ((array) $picks as $opt) {
                if (isset($counts[$opt])) {
                    $counts[$opt]++;
                }
            }
        }
        foreach ($poll['options'] as &$opt) {
            $opt['votes'] = $counts[$opt['id']];
        }
        unset($opt);

        $poll['voters'] = $voters ?: (object) [];
        $poll['total_votes'] = array_sum($counts);
        $meta['poll'] = $poll;
        $feedPost->meta = $meta;
        $feedPost->save();

        // First vote on this poll only (idempotent ledger key = feed_post id).
        if (! $hadVote && ! empty($voters[$pid] ?? [])) {
            $participation = Participation::find($request->attributes->get('participation_id'));
            if ($participation) {
                app(GamificationScorer::class)->queue(
                    (int) $participation->organization_id,
                    (int) $feedPost->event_id,
                    (int) $participation->id,
                    'vote_feed_polls',
                    'feed_post',
                    (int) $feedPost->id,
                );
            }
        }

        return response()->json(['data' => new FeedPostResource($feedPost->fresh())]);
    }

    /** GET the published comments on a post, oldest first. */
    public function comments(string $event, string $post, Request $request): JsonResponse
    {
        $feedPost = $this->resolvePost($request, $post);

        $comments = FeedComment::where('post_id', $feedPost->id)
            ->where('status', 'published')
            ->orderBy('id')
            ->get();

        FeedPost::warmAuthorCache($comments);

        return response()->json([
            'data' => $comments->map(fn (FeedComment $c) => $this->commentPayload($c))->values(),
        ]);
    }

    public function comment(string $event, string $post, Request $request): JsonResponse
    {
        $this->abortUnlessOp($request, 'comment_feed_post', 'You are not allowed to comment on posts.');

        $feedPost = $this->resolvePost($request, $post);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'mentions' => ['nullable', 'array', 'max:20'],
            'mentions.*' => ['uuid'],
        ]);

        $pid = (int) $request->attributes->get('participation_id');
        $mentions = FeedMentions::resolve((int) $feedPost->event_id, $data['mentions'] ?? []);
        $comment = FeedComment::create([
            'post_id' => $feedPost->id,
            'event_id' => $feedPost->event_id,
            'author_type' => 'participation',
            'author_id' => $pid,
            'body' => $data['body'],
            'status' => 'published',
            'meta' => $mentions !== [] ? ['mentions' => $mentions] : null,
        ]);

        $feedPost->increment('comment_count');

        $participation = Participation::find($pid);
        if ($participation) {
            app(GamificationScorer::class)->queue(
                (int) $participation->organization_id,
                (int) $feedPost->event_id,
                $pid,
                'comment_feed_post',
                'feed_comment',
                (int) $comment->id,
            );
        }

        FeedMentions::notify((int) $feedPost->event_id, $pid, $mentions, 'mentioned you in a comment');

        return response()->json(['data' => $this->commentPayload($comment)], 201);
    }

    /**
     * Toggle the current participant's reaction on a post. First tap reacts,
     * second tap removes it — keeps the denormalized counter in step.
     */
    public function react(string $event, string $post, Request $request): JsonResponse
    {
        $this->abortUnlessOp($request, 'feed_post_likes', 'You are not allowed to like posts.');

        $pid = $request->attributes->get('participation_id');
        $feedPost = $this->resolvePost($request, $post);

        $data = $request->validate(['type' => ['nullable', 'in:like,love,clap,insightful']]);

        $existing = FeedReaction::where('reactable_type', 'feed_post')
            ->where('reactable_id', $feedPost->id)
            ->where('participation_id', $pid)
            ->first();

        if ($existing) {
            $existing->delete();
            if ($feedPost->reaction_count > 0) {
                $feedPost->decrement('reaction_count');
            }
            $reacted = false;
        } else {
            FeedReaction::create([
                'reactable_type' => 'feed_post',
                'reactable_id' => $feedPost->id,
                'participation_id' => $pid,
                'event_id' => $feedPost->event_id,
                'type' => $data['type'] ?? 'like',
            ]);
            $feedPost->increment('reaction_count');
            $reacted = true;

            $participation = Participation::find($pid);
            if ($participation) {
                app(GamificationScorer::class)->queue(
                    (int) $participation->organization_id,
                    (int) $feedPost->event_id,
                    (int) $pid,
                    'feed_post_likes',
                    'feed_post',
                    (int) $feedPost->id,
                );
            }
        }

        return response()->json([
            'reacted' => $reacted,
            'reactions' => $feedPost->fresh()->reaction_count,
        ]);
    }

    /**
     * Resolve a post uuid within the current event, published only: pending /
     * rejected posts aren't live on the wall, so they accept no engagement
     * (comments, reactions, votes) even from their author.
     */
    protected function resolvePost(Request $request, string $uuid): FeedPost
    {
        return FeedPost::where('uuid', $uuid)
            ->where('event_id', $request->attributes->get('event_id'))
            ->where('status', 'published')
            ->firstOrFail();
    }

    /** Enforce Communication › Functionality for the caller's role. */
    protected function abortUnlessOp(Request $request, string $operation, string $message): void
    {
        $event = Event::with('settings')->findOrFail($request->attributes->get('event_id'));
        $participation = Participation::findOrFail($request->attributes->get('participation_id'));

        CommunicationCapabilities::abortUnless(
            $participation,
            CommunicationCapabilities::communication($event),
            $operation,
            $message,
        );
    }

    /** Shared comment projection (author label + avatar + timestamp). */
    protected function commentPayload(FeedComment $c): array
    {
        $author = FeedPost::authorInfo($c->author_type, $c->author_id);

        return [
            'id' => $c->id,
            'body' => $c->body,
            'author' => $author['name'],
            'author_avatar' => $author['avatar'],
            'author_role' => $author['role'],
            'mentions' => data_get($c->meta, 'mentions', []),
            'created_at' => $c->created_at?->toIso8601String(),
        ];
    }
}
