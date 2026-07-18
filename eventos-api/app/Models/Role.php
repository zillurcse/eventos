<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Named role, platform- or tenant-scoped (architecture §6.1).
 * organization_id NULL = platform/global role.
 */
class Role extends Model
{
    // organization_id, scope and is_system are privilege/tenant columns; the app
    // never creates roles at runtime (seeder-only), so only descriptive fields
    // are mass-assignable.
    protected $fillable = [
        'name', 'description',
    ];

    protected $casts = ['is_system' => 'boolean'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }
}
