<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContestEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $author = $this->authorInfo();
        $pid = (int) $request->attributes->get('participation_id');

        return [
            'id' => $this->uuid,
            'kind' => $this->kind,
            'body' => $this->body,
            'attachments' => $this->attachments ?? [],
            'status' => $this->status,
            'is_winner' => (bool) $this->is_winner,
            'rank' => $this->rank,
            'awarded_points' => (int) $this->awarded_points,
            'like_count' => (int) $this->like_count,
            'comment_count' => (int) $this->comment_count,
            'author' => $author['name'],
            'author_avatar' => $author['avatar'],
            'author_headline' => $author['headline'],
            'is_mine' => $pid > 0 && (int) $this->participation_id === $pid,
            // Set when the query eager-loads likes constrained to this viewer.
            'liked' => $this->relationLoaded('likes') ? $this->likes->isNotEmpty() : false,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
