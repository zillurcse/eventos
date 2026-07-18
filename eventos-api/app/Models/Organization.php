<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tenant root. NOT itself org-scoped — it IS the tenant (architecture §6.1).
 */
class Organization extends Model
{
    use HasUuid, SoftDeletes;

    // status (governance) and owner_user_id (ownership) are set via forceFill at
    // their trusted write sites (provisioner + super-admin), never mass-assigned.
    protected $fillable = [
        'name', 'slug', 'default_locale', 'default_timezone', 'default_currency',
        'billing_email', 'data_region', 'meta',
    ];

    protected $casts = ['meta' => 'array'];

    public function settings(): HasOne
    {
        return $this->hasOne(OrganizationSetting::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
