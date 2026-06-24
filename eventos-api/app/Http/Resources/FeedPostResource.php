<?php

namespace App\Http\Resources;

use App\Models\Participation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeedPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'body' => $this->body,
            'visibility' => $this->visibility,
            'is_pinned' => (bool) $this->is_pinned,
            'status' => $this->status,
            'comment_count' => (int) $this->comment_count,
            'reaction_count' => (int) $this->reaction_count,
            'author' => $this->authorLabel(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    protected function authorLabel(): string
    {
        if ($this->author_type === 'participation') {
            $p = Participation::with('contact')->find($this->author_id);

            return $p && $p->contact
                ? (trim($p->contact->first_name.' '.$p->contact->last_name) ?: 'Attendee')
                : 'Attendee';
        }

        return 'Organizer';
    }
}
