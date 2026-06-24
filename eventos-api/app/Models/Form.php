<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Form definition — registration, speaker, partner, survey (architecture §6.12).
 */
class Form extends Model
{
    use BelongsToOrganization, SoftDeletes, HasUuid;

    protected $guarded = [];

    protected $casts = ['settings' => 'array'];

    public function sections(): HasMany
    {
        return $this->hasMany(FormSection::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->orderBy('sort_order');
    }

    public function logicRules(): HasMany
    {
        return $this->hasMany(FormLogicRule::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }
}
