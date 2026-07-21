<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One attendee's participation in a contest — either a submission (`entry`) or
 * a comment on someone else's submission (`comment`, with `parent_id` set).
 * See §2026_07_21_000002.
 */
class ContestEntry extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'attachments' => 'array',
        'meta' => 'array',
        'is_winner' => 'boolean',
        'rank' => 'integer',
        'awarded_points' => 'integer',
        'like_count' => 'integer',
        'comment_count' => 'integer',
    ];

    public function contest(): BelongsTo
    {
        return $this->belongsTo(Contest::class);
    }

    public function participation(): BelongsTo
    {
        return $this->belongsTo(Participation::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->where('kind', 'comment');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(ContestEntryLike::class);
    }

    /** Display name + avatar of the attendee who posted this. */
    public function authorInfo(): array
    {
        $p = $this->relationLoaded('participation')
            ? $this->participation
            : Participation::with('contact')->find($this->participation_id);

        if (! $p) {
            return ['name' => 'Attendee', 'avatar' => null, 'headline' => null];
        }

        $name = trim(($p->contact->first_name ?? '').' '.($p->contact->last_name ?? ''));

        return [
            'name' => $name ?: 'Attendee',
            'avatar' => $p->profile_data['image_url'] ?? null,
            'headline' => $p->profile_data['designation'] ?? ($p->contact->company ?? null),
        ];
    }
}
