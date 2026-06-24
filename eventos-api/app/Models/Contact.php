<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasDynamicFields;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Global person within an organization — unique by email per org, joined to
 * many events via participations (architecture §6.4).
 */
class Contact extends Model
{
    use BelongsToOrganization, SoftDeletes, HasUuid, Auditable, HasDynamicFields;

    protected $guarded = [];

    protected $casts = [
        'profile_data' => 'array',
        'meta' => 'array',
        'marketing_opt_in' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function participations(): HasMany
    {
        return $this->hasMany(Participation::class);
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
