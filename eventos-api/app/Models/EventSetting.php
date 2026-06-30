<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventSetting extends Model
{
    use BelongsToOrganization;

    protected $guarded = [];

    protected $casts = [
        'branding' => 'array',
        'theme' => 'array',
        'modules_enabled' => 'array',
        'networking_config' => 'array',
        'privacy' => 'array',
        'login' => 'array',
        'domain' => 'array',
        'navigation' => 'array',
        'seo' => 'array',
        'filters' => 'array',
        'banners' => 'array',
        'faqs' => 'array',
        'testimonials' => 'array',
        'social' => 'array',
        'notifications' => 'array',
        'chat' => 'array',
        'meeting' => 'array',
        'lounge' => 'array',
        'communication' => 'array',
        'mobile_access_panel' => 'array',
        'registration_open' => 'datetime',
        'registration_close' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
