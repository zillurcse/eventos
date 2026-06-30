<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An event advertisement (AD Managements section). Carousel images + targeting
 * are JSON; tenant isolation via BelongsToOrganization + RLS.
 */
class EventAd extends Model
{
    use BelongsToOrganization, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'images' => 'array',
        'targeted_groups' => 'array',
        'targeted_pages' => 'array',
        'is_active' => 'boolean',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'impressions' => 'integer',
        'clicks' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
