<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A floor plan for the canvas editor (modules/floor.expouse). Canvas state is
 * stored as JSON; tenant isolation via BelongsToOrganization + RLS.
 */
class Floor extends Model
{
    use BelongsToOrganization, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'dimensions' => 'array',
        'floor_area' => 'array',
        'objects' => 'array',
        'dom_elements' => 'array',
        'offset' => 'array',
        'zoom' => 'integer',
        'wall_generated' => 'boolean',
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
