<?php

namespace App\Http\Resources;

use App\Models\FeedPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeedPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $author = FeedPost::authorInfo($this->author_type, $this->author_id);
        $meta = $this->meta ?? [];
        $pid = $request->attributes->get('participation_id');

        return [
            'id' => $this->uuid,
            'type' => $meta['type'] ?? 'text',
            'body' => $this->body,
            'visibility' => $this->visibility,
            'is_pinned' => (bool) $this->is_pinned,
            'status' => $this->status,
            'comment_count' => (int) $this->comment_count,
            'reaction_count' => (int) $this->reaction_count,
            'author' => $author['name'],
            'author_avatar' => $author['avatar'],
            'author_role' => $author['role'],
            'is_mine' => $this->author_type === 'participation' && (int) $this->author_id === (int) $pid,
            // Whether the current participant has reacted — set when the index
            // query eager-loads reactions constrained to this participant.
            'reacted' => $this->relationLoaded('reactions') ? $this->reactions->isNotEmpty() : false,
            'attachments' => $meta['attachments'] ?? [],
            'tags' => $meta['tags'] ?? [],
            'poll' => $this->pollPayload($meta, $request),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Public poll shape: options with counts, total, and the current viewer's
     * picks (`my_vote`). The raw voter map is never exposed.
     */
    protected function pollPayload(array $meta, Request $request): ?array
    {
        $poll = $meta['poll'] ?? null;
        if (! $poll) {
            return null;
        }

        $pid = (string) $request->attributes->get('participation_id');
        $voters = (array) ($poll['voters'] ?? []);

        return [
            'options' => array_map(fn ($o) => [
                'id' => $o['id'],
                'text' => $o['text'],
                'votes' => (int) ($o['votes'] ?? 0),
            ], $poll['options'] ?? []),
            'allow_multiple' => (bool) ($poll['allow_multiple'] ?? false),
            'total_votes' => (int) ($poll['total_votes'] ?? 0),
            'my_vote' => array_values((array) ($voters[$pid] ?? [])),
        ];
    }
}
