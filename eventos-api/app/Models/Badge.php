<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class Badge extends Model
{
    use BelongsToOrganization;

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
        'settings' => 'array',
        'data' => 'array',
        'properties' => 'array',
        'validation' => 'array',
        'default_value' => 'array',
        'content' => 'array',
        'design' => 'array',
        'merge_data' => 'array',
        'placements' => 'array',
        'profile_data' => 'array',
        'entitlements' => 'array',
        'resources' => 'array',
        'audience' => 'array',
        'channels' => 'array',
        'rules' => 'array',
        'limits' => 'array',
        'old_values' => 'array',
        'new_values' => 'array',
        'details' => 'array',
        'overrides' => 'array',
        'feature_overrides' => 'array',
        'notification_defaults' => 'array',
        'security' => 'array',
        'branding' => 'array',
        'theme' => 'array',
        'modules_enabled' => 'array',
        'networking_config' => 'array',
        'privacy' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'sales_start' => 'datetime',
        'sales_end' => 'datetime',
        'expires_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'read_at' => 'datetime',
        'paid_at' => 'datetime',
        'due_at' => 'datetime',
        'issued_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'canceled_at' => 'datetime',
        'responded_at' => 'datetime',
        'printed_at' => 'datetime',
        'scanned_at' => 'datetime',
        'last_used_at' => 'datetime',
        'submitted_at' => 'datetime',
        'generated_at' => 'datetime',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'registration_open' => 'datetime',
        'registration_close' => 'datetime',
        'checked_in_at' => 'datetime',
        'joined_at' => 'datetime',
        'invited_at' => 'datetime',
        'last_login_at' => 'datetime',
        'opens_at' => 'datetime',
        'closes_at' => 'datetime',
    ];
}
