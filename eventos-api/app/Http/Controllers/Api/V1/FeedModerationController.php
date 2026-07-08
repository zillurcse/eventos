<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\NewFeedPost;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\FeedPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Organizer-side Activity Feed management (Engagement › Manage Activity Feed).
 * Tenant-scoped. Lists attendee posts by moderation status (pending /
 * published / rejected), lets organizers approve or reject them, and toggles
 * the event's moderation switch (event.meta.feed_moderation). While the switch
 * is on, new attendee posts start as `pending` and only reach the attendee
 * feed once approved (see FeedController@store).
 */
class FeedModerationController extends Controller
{
    /** Statuses the admin UI tabs map onto. */
    private const STATUSES = ['pending', 'published', 'rejected'];

    public function index(string $uuid, Request $request): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $data = $request->validate([
            'status' => ['nullable', 'in:pending,published,rejected'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        // Tab badges always reflect the whole event, not the current search.
        $counts = FeedPost::where('event_id', $event->id)
            ->selectRaw('status, count(*) AS c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $query = FeedPost::where('event_id', $event->id)
            ->where('status', $data['status'] ?? 'pending');

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

        $posts = $query->latest('id')->paginate(24)->withQueryString();

        return response()->json([
            'moderate' => (bool) data_get($event->meta, 'feed_moderation', false),
            'counts' => collect(self::STATUSES)->mapWithKeys(
                fn ($s) => [$s => (int) ($counts[$s] ?? 0)]
            ),
            'data' => $posts->getCollection()->map(fn (FeedPost $p) => $this->payload($p)),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    /** Flip the event-wide moderation switch on/off. */
    public function settings(string $uuid, Request $request): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $data = $request->validate(['moderate' => ['required', 'boolean']]);

        $meta = $event->meta ?? [];
        $meta['feed_moderation'] = $data['moderate'];
        $event->meta = $meta;
        $event->save();

        return response()->json(['moderate' => $data['moderate']]);
    }

    /** Approve (publish) or reject a single post. */
    public function decide(string $uuid, string $post, Request $request): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();
        $feedPost = FeedPost::where('uuid', $post)->where('event_id', $event->id)->firstOrFail();

        $data = $request->validate(['action' => ['required', 'in:approve,reject']]);

        $wasVisible = $feedPost->status === 'published';
        $feedPost->status = $data['action'] === 'approve' ? 'published' : 'rejected';
        $feedPost->save();

        // Fan out over Reverb only when the post first becomes visible, so
        // attendee feeds pick up newly approved posts live.
        if ($feedPost->status === 'published' && ! $wasVisible) {
            broadcast(new NewFeedPost($feedPost));
        }

        return response()->json(['data' => $this->payload($feedPost)]);
    }

    /** Admin projection of a post (author label + engagement counters). */
    protected function payload(FeedPost $p): array
    {
        $author = FeedPost::authorInfo($p->author_type, $p->author_id);
        $meta = $p->meta ?? [];

        return [
            'id' => $p->uuid,
            'type' => $meta['type'] ?? 'text',
            'body' => $p->body,
            'status' => $p->status,
            'is_pinned' => (bool) $p->is_pinned,
            'author' => $author['name'],
            'author_avatar' => $author['avatar'],
            'author_role' => $author['role'],
            'likes' => (int) $p->reaction_count,
            'comments' => (int) $p->comment_count,
            'reports' => count((array) ($meta['reports'] ?? [])),
            'attachments' => $meta['attachments'] ?? [],
            'tags' => $meta['tags'] ?? [],
            'poll_options' => array_column($meta['poll']['options'] ?? [], 'text'),
            'created_at' => $p->created_at?->toIso8601String(),
        ];
    }
}
