<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\NewFeedPost;
use App\Http\Controllers\Controller;
use App\Http\Resources\FeedPostResource;
use App\Models\FeedComment;
use App\Models\FeedPost;
use App\Models\FeedReaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Event feed (architecture §6.6). Acts as the resolved participation
 * (ResolveParticipant middleware). New posts fan out over Reverb.
 */
class FeedController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return FeedPostResource::collection(
            FeedPost::where('event_id', $request->attributes->get('event_id'))
                ->whereIn('status', ['published'])
                ->orderByDesc('is_pinned')
                ->latest('id')
                ->paginate(20)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'visibility' => ['nullable', 'in:public,attendees,group'],
        ]);

        $post = FeedPost::create([
            'event_id' => $request->attributes->get('event_id'),
            'author_type' => 'participation',
            'author_id' => $request->attributes->get('participation_id'),
            'body' => $data['body'],
            'visibility' => $data['visibility'] ?? 'attendees',
            'status' => 'published',
        ]);

        broadcast(new NewFeedPost($post));

        return response()->json(['data' => new FeedPostResource($post)], 201);
    }

    public function comment(string $event, string $post, Request $request): JsonResponse
    {
        $eventId = $request->attributes->get('event_id');
        $feedPost = FeedPost::where('uuid', $post)->where('event_id', $eventId)->firstOrFail();

        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $comment = FeedComment::create([
            'post_id' => $feedPost->id,
            'event_id' => $eventId,
            'author_type' => 'participation',
            'author_id' => $request->attributes->get('participation_id'),
            'body' => $data['body'],
            'status' => 'published',
        ]);

        $feedPost->increment('comment_count');

        return response()->json(['data' => ['id' => $comment->id, 'body' => $comment->body]], 201);
    }

    public function react(string $event, string $post, Request $request): JsonResponse
    {
        $eventId = $request->attributes->get('event_id');
        $feedPost = FeedPost::where('uuid', $post)->where('event_id', $eventId)->firstOrFail();

        $data = $request->validate(['type' => ['nullable', 'in:like,love,clap,insightful']]);

        $reaction = FeedReaction::firstOrCreate(
            [
                'reactable_type' => 'feed_post',
                'reactable_id' => $feedPost->id,
                'participation_id' => $request->attributes->get('participation_id'),
            ],
            ['event_id' => $eventId, 'type' => $data['type'] ?? 'like'],
        );

        if ($reaction->wasRecentlyCreated) {
            $feedPost->increment('reaction_count');
        }

        return response()->json(['reactions' => $feedPost->fresh()->reaction_count]);
    }
}
