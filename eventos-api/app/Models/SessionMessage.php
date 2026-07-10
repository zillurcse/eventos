<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A live-session side-panel message — group Chat (kind=chat) or a Q&A question
 * (kind=question, with upvotes). Tenant-isolated via BelongsToOrganization + RLS.
 */
class SessionMessage extends Model
{
    use BelongsToOrganization;

    protected $guarded = [];

    protected $casts = [
        'upvotes' => 'integer',
        'is_answered' => 'boolean',
        'meta' => 'array',
    ];

    public function participation(): BelongsTo
    {
        return $this->belongsTo(Participation::class);
    }
}
