<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'branding' => 'array',
        'feature_overrides' => 'array',
        'notification_defaults' => 'array',
        'security' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
