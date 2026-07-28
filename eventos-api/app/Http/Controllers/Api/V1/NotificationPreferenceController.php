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
 * Catalogue + defaults live on {@see NotificationPreference::CATEGORIES}.
 */
class NotificationPreferenceController extends Controller
{
    /** GET /notification-preferences — the full fixed category list, saved rows merged over defaults. */
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->merged($request->user()->id)]);
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
        collect($rows)->each(function (array $row) use ($userId) {
            $default = NotificationPreference::CATEGORIES[$row['category']] ?? ['email' => true, 'in_app' => true];

            NotificationPreference::on('pgsql_admin')->updateOrCreate(
                ['user_id' => $userId, 'category' => $row['category'], 'organization_id' => null, 'event_id' => null],
                [
                    'email' => $row['email'] ?? $default['email'],
                    'in_app' => $row['in_app'] ?? $default['in_app'],
                    'push' => $row['push'] ?? true,
                    'sms' => $row['sms'] ?? false,
                ],
            );
        });

        // Always return the full merged catalogue so the settings page stays consistent.
        $data = $this->merged($userId);

        return response()->json([
            'data' => $request->has('prefs') ? $data : collect($data)->firstWhere('category', $rows[0]['category']),
        ]);
    }

    /** @return list<array{category: string, email: bool, in_app: bool}> */
    private function merged(int $userId): array
    {
        $saved = NotificationPreference::on('pgsql_admin')
            ->where('user_id', $userId)
            ->whereNull('organization_id')
            ->whereNull('event_id')
            ->get()
            ->keyBy('category');

        return collect(NotificationPreference::CATEGORIES)->map(function (array $default, string $category) use ($saved) {
            $row = $saved->get($category);

            return [
                'category' => $category,
                'email' => $row ? (bool) $row->email : $default['email'],
                'in_app' => $row ? (bool) $row->in_app : $default['in_app'],
            ];
        })->values()->all();
    }
}
