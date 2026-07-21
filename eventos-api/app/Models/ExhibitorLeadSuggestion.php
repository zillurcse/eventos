<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The team's decision on one recommended attendee: who owns it, whether a
 * connection request went out, whether it was dismissed. The interest score is
 * recomputed live (see LeadRecommendationService) — what lives here is only
 * what a human decided.
 */
class ExhibitorLeadSuggestion extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $guarded = [];

    protected $casts = [
        'signals' => 'array',
        'meta' => 'array',
        'requested_at' => 'datetime',
        'dismissed_at' => 'datetime',
    ];

    public const STATUSES = ['new', 'assigned', 'requested', 'dismissed'];

    public function exhibitor(): BelongsTo
    {
        return $this->belongsTo(Exhibitor::class);
    }

    public function participation(): BelongsTo
    {
        return $this->belongsTo(Participation::class);
    }

    public function assignedMember(): BelongsTo
    {
        return $this->belongsTo(ExhibitorMember::class, 'assigned_member_id');
    }
}
