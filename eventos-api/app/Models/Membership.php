<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * User ↔ organization link with role scope (architecture §6.1).
 */
class Membership extends Model
{
    use SoftDeletes;

    // organization_id + status are included on purpose: Membership has no
    // BelongsToOrganization auto-fill, so the org must be mass-assigned at
    // create (and it, plus status, only ever receive server/validated values).
    protected $fillable = [
        'user_id', 'organization_id', 'status', 'invited_by', 'invited_at', 'joined_at',
    ];

    protected $casts = [
        'invited_at' => 'datetime',
        'joined_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'membership_role');
    }
}
