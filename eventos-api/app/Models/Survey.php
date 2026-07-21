<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A survey (Event Engagement › Surveys). Questions live on the linked `form`
 * as form_fields — no parallel question model (architecture §6.6/§6.12).
 */
class Survey extends Model
{
    use BelongsToOrganization, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'opens_at' => 'datetime',
        'closes_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    /** Time-based lifecycle used to bucket the Surveys list into tabs. */
    public function phase(): string
    {
        $now = now();
        if ($this->opens_at && $now->lt($this->opens_at)) {
            return 'upcoming';
        }
        if ($this->closes_at && $now->gt($this->closes_at)) {
            return 'ended';
        }

        return 'ongoing';
    }
}
