<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    /**
     * Fixed Profile › Notifications catalogue and pre-save defaults.
     * Meetings/Messages email and Meeting Status / Messages / Organiser in-app
     * start on so existing delivery keeps working until the user opts out;
     * everything else starts off until its trigger is fully wired.
     *
     * @var array<string, array{email: bool, in_app: bool}>
     */
    public const CATEGORIES = [
        'meetings' => ['email' => true, 'in_app' => true],
        'messages' => ['email' => true, 'in_app' => true],
        'profile_views' => ['email' => false, 'in_app' => false],
        'mentions' => ['email' => false, 'in_app' => false],
        'admin_post' => ['email' => false, 'in_app' => false],
        'new_activity' => ['email' => false, 'in_app' => false],
        'organiser' => ['email' => false, 'in_app' => true],
        'meeting_status' => ['email' => false, 'in_app' => true],
        'session_live' => ['email' => false, 'in_app' => false],
    ];

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
