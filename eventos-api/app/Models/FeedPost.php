<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeedPost extends Model
{
    use BelongsToOrganization, SoftDeletes, HasUuid;

    protected $guarded = [];

    public function comments(): HasMany
    {
        return $this->hasMany(FeedComment::class, 'post_id');
    }

    /**
     * Reactions on this post. The reactable morph stores a literal string type
     * ('feed_post'), not a class morph-map key, so the relation is scoped by
     * hand rather than via morphMany.
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(FeedReaction::class, 'reactable_id')
            ->where('reactable_type', 'feed_post');
    }

    /**
     * Display projection for a feed author (post or comment). Authors are
     * polymorphic: a participation (attendee) or a user (organizer). Attendee
     * avatars come from participation.profile_data.image_url.
     *
     * @return array{name: string, avatar: string|null, role: string}
     */
    public static function authorInfo(?string $type, ?int $id): array
    {
        if ($type === 'participation' && $id) {
            $p = Participation::with('contact')->find($id);
            if ($p) {
                $name = trim(($p->contact->first_name ?? '').' '.($p->contact->last_name ?? ''));

                return [
                    'name' => $name ?: 'Attendee',
                    'avatar' => $p->profile_data['image_url'] ?? null,
                    'role' => 'attendee',
                ];
            }

            return ['name' => 'Attendee', 'avatar' => null, 'role' => 'attendee'];
        }

        return ['name' => 'Organizer', 'avatar' => null, 'role' => 'organizer'];
    }

    protected $casts = [
        'meta' => 'array',
        'settings' => 'array',
        'data' => 'array',
        'properties' => 'array',
        'validation' => 'array',
        'default_value' => 'array',
        'content' => 'array',
        'design' => 'array',
        'merge_data' => 'array',
        'placements' => 'array',
        'profile_data' => 'array',
        'entitlements' => 'array',
        'resources' => 'array',
        'audience' => 'array',
        'channels' => 'array',
        'rules' => 'array',
        'limits' => 'array',
        'old_values' => 'array',
        'new_values' => 'array',
        'details' => 'array',
        'overrides' => 'array',
        'feature_overrides' => 'array',
        'notification_defaults' => 'array',
        'security' => 'array',
        'branding' => 'array',
        'theme' => 'array',
        'modules_enabled' => 'array',
        'networking_config' => 'array',
        'privacy' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'sales_start' => 'datetime',
        'sales_end' => 'datetime',
        'expires_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'read_at' => 'datetime',
        'paid_at' => 'datetime',
        'due_at' => 'datetime',
        'issued_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'canceled_at' => 'datetime',
        'responded_at' => 'datetime',
        'printed_at' => 'datetime',
        'scanned_at' => 'datetime',
        'last_used_at' => 'datetime',
        'submitted_at' => 'datetime',
        'generated_at' => 'datetime',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'registration_open' => 'datetime',
        'registration_close' => 'datetime',
        'checked_in_at' => 'datetime',
        'joined_at' => 'datetime',
        'invited_at' => 'datetime',
        'last_login_at' => 'datetime',
        'opens_at' => 'datetime',
        'closes_at' => 'datetime',
    ];
}
