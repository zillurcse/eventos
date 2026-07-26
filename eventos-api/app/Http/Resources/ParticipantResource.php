<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A person taking part in an event (a participation + its contact). Powers the
 * organizer "Users" screens. Block state is kept in participation.meta.blocked.
 */
class ParticipantResource extends JsonResource
{
    /**
     * Pre-resolved contact photo URLs keyed by files.id (batch-loaded by the
     * controller so the index endpoint doesn't N+1).
     *
     * @var array<int, string>
     */
    public static array $photoUrls = [];

    public function toArray(Request $request): array
    {
        $c = $this->contact;
        $meta = $this->meta ?? [];
        $profile = $this->profile_data ?? [];
        $contactProfile = $c?->profile_data ?? [];
        $contactPhotoUrl = static::$photoUrls[$c?->photo_file_id] ?? null;

        // Same precedence as the attendee directory: Edit Profile writes
        // profile_data.avatar_url; speakers store profile_data.image_url;
        // contact.photo_file_id is the shared-person fallback.
        $avatar = $profile['avatar_url']
            ?? ($profile['image_url']
            ?? ($meta['avatar_url']
            ?? ($contactPhotoUrl
            ?? ($contactProfile['avatar_url']
            ?? ($contactProfile['image_url'] ?? null)))));

        return [
            'id'            => $this->uuid,
            'name'          => $c ? trim(($c->first_name ?? '').' '.($c->last_name ?? '')) ?: null : null,
            'email'         => $c?->email,
            'phone'         => $c?->phone,
            'company'       => $c?->company,
            'job_title'     => $c?->job_title,
            'role'          => $this->role,
            'status'        => $this->status,
            'blocked'       => (bool) ($meta['blocked'] ?? false),
            'has_login'     => (bool) ($c?->user_id),
            'checked_in'    => $this->status === 'checked_in' || $this->checked_in_at !== null,
            'checked_in_at' => $this->checked_in_at?->toIso8601String(),
            'registered_at' => $this->created_at?->toIso8601String(),
            'avatar_url'    => ($avatar !== null && $avatar !== '') ? $avatar : null,
        ];
    }
}
