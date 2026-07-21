<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A badge design for the canvas editor (modules/badge.expouse). Canvas state is
 * stored as JSON; tenant isolation via BelongsToOrganization + RLS. Distinct
 * from the check-in `Badge` model.
 */
class BadgeDesign extends Model
{
    use BelongsToOrganization, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'badge_json' => 'array',
        'font_json' => 'array',
        'back_json' => 'array',
        'layers' => 'array',
        'meta' => 'array',
        'is_default' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
