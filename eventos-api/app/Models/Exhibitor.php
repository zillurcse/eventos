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
 * Unified exhibitor | sponsor (type discriminator) — architecture §6.3, §10.4.
 */
class Exhibitor extends Model
{
    use BelongsToOrganization, SoftDeletes, HasUuid, Auditable, HasDynamicFields;

    protected $guarded = [];

    protected $casts = [
        'placements' => 'array',
        'profile_data' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ExhibitorPackage::class, 'package_id');
    }

    public function adminContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'admin_contact_id');
    }

    public function logoFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'logo_file_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ExhibitorMember::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ExhibitorDocument::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(ExhibitorProduct::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(ExhibitorProject::class);
    }

    public function booths(): HasMany
    {
        return $this->hasMany(Booth::class);
    }

    public function scopeExhibitors($q)
    {
        return $q->where('type', 'exhibitor');
    }

    public function scopeSponsors($q)
    {
        return $q->where('type', 'sponsor');
    }
}
