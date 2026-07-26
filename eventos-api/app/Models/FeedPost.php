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
     * Request-scoped author map keyed by "participation:{id}". When warmed via
     * warmAuthorCache(), authorInfo() is O(1) and a feed page avoids N+1 finds.
     *
     * @var array<string, array{name: string, avatar: string|null, role: string}>|null
     */
    protected static ?array $authorCache = null;

    /**
     * Batch-load participation authors for a collection of posts/comments so
     * FeedPostResource / commentPayload don't hit the DB once per row.
     *
     * @param  iterable<int, object|array{author_type?: string|null, author_id?: int|null}>  $items
     */
    public static function warmAuthorCache(iterable $items): void
    {
        $ids = [];
        foreach ($items as $item) {
            $type = is_array($item) ? ($item['author_type'] ?? null) : ($item->author_type ?? null);
            $id = is_array($item) ? ($item['author_id'] ?? null) : ($item->author_id ?? null);
            if ($type === 'participation' && $id) {
                $ids[(int) $id] = true;
            }
        }

        $ids = array_keys($ids);
        static::$authorCache = [];

        if ($ids === []) {
            return;
        }

        $parts = Participation::with('contact')->whereIn('id', $ids)->get()->keyBy('id');

        foreach ($ids as $id) {
            static::$authorCache['participation:'.$id] = static::formatAuthor($parts->get($id));
        }
    }

    public static function clearAuthorCache(): void
    {
        static::$authorCache = null;
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
            $key = 'participation:'.$id;

            if (static::$authorCache !== null) {
                return static::$authorCache[$key] ?? ['name' => 'Attendee', 'avatar' => null, 'role' => 'attendee'];
            }

            return static::formatAuthor(Participation::with('contact')->find($id));
        }

        return ['name' => 'Organizer', 'avatar' => null, 'role' => 'organizer'];
    }

    /**
     * @return array{name: string, avatar: string|null, role: string}
     */
    protected static function formatAuthor(?Participation $p): array
    {
        if (! $p) {
            return ['name' => 'Attendee', 'avatar' => null, 'role' => 'attendee'];
        }

        $name = trim(($p->contact->first_name ?? '').' '.($p->contact->last_name ?? ''));

        return [
            'name' => $name ?: 'Attendee',
            'avatar' => $p->profile_data['image_url'] ?? null,
            'role' => 'attendee',
        ];
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
