<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A signed-in user's notification preferences (Profile › Account Settings ›
 * Notifications) — global across every org/event they belong to, one row per
 * category on `notification_preferences` (organization_id/event_id left NULL).
 * `CATEGORIES` is the fixed set the settings page renders, with the default a
 * category gets before the user has ever touched it — Meetings/Messages start
 * opted in to email since those are the two channels already wired end to end
 * (§6.7); the rest start off until their triggering feature exists.
 */
class NotificationPreferenceController extends Controller
{
    public const CATEGORIES = [
        'meetings' => ['email' => true, 'in_app' => false],
        'messages' => ['email' => true, 'in_app' => false],
        'profile_views' => ['email' => false, 'in_app' => false],
        'mentions' => ['email' => false, 'in_app' => false],
        'admin_post' => ['email' => false, 'in_app' => false],
        'new_activity' => ['email' => false, 'in_app' => false],
        'organiser' => ['email' => false, 'in_app' => false],
        'meeting_status' => ['email' => false, 'in_app' => false],
        'session_live' => ['email' => false, 'in_app' => false],
    ];

    /** GET /notification-preferences — the full fixed category list, saved rows merged over defaults. */
    public function index(Request $request): JsonResponse
    {
        $saved = NotificationPreference::on('pgsql_admin')
            ->where('user_id', $request->user()->id)
            ->whereNull('organization_id')
            ->whereNull('event_id')
            ->get()
            ->keyBy('category');

        $data = collect(self::CATEGORIES)->map(function (array $default, string $category) use ($saved) {
            $row = $saved->get($category);

            return [
                'category' => $category,
                'email' => $row ? (bool) $row->email : $default['email'],
                'in_app' => $row ? (bool) $row->in_app : $default['in_app'],
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    /**
     * PUT /notification-preferences — either one category `{category,email,in_app}`
     * or the whole settings-page Save `{prefs:[{category,email,in_app}, …]}`.
     */
    public function update(Request $request): JsonResponse
    {
        $rows = $request->has('prefs')
            ? $request->validate(['prefs' => ['required', 'array'], 'prefs.*.category' => ['required', 'string', 'max:60'], 'prefs.*.email' => ['boolean'], 'prefs.*.in_app' => ['boolean']])['prefs']
            : [$request->validate(['category' => ['required', 'string', 'max:60'], 'email' => ['boolean'], 'push' => ['boolean'], 'sms' => ['boolean'], 'in_app' => ['boolean']])];

        $userId = $request->user()->id;
        $saved = collect($rows)->map(function (array $row) use ($userId) {
            $default = self::CATEGORIES[$row['category']] ?? ['email' => true, 'in_app' => true];

            $pref = NotificationPreference::on('pgsql_admin')->updateOrCreate(
                ['user_id' => $userId, 'category' => $row['category'], 'organization_id' => null, 'event_id' => null],
                [
                    'email' => $row['email'] ?? $default['email'],
                    'in_app' => $row['in_app'] ?? $default['in_app'],
                    'push' => $row['push'] ?? true,
                    'sms' => $row['sms'] ?? false,
                ],
            );

            return ['category' => $pref->category, 'email' => (bool) $pref->email, 'in_app' => (bool) $pref->in_app];
        });

        return response()->json(['data' => $request->has('prefs') ? $saved->values() : $saved->first()]);
    }
}
