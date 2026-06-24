<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $prefs = NotificationPreference::on('pgsql_admin')
            ->where('user_id', $request->user()->id)
            ->get()
            ->map(fn ($p) => [
                'category' => $p->category,
                'email' => (bool) $p->email,
                'push' => (bool) $p->push,
                'sms' => (bool) $p->sms,
                'in_app' => (bool) $p->in_app,
            ]);

        return response()->json(['data' => $prefs]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'max:60'],
            'email' => ['boolean'],
            'push' => ['boolean'],
            'sms' => ['boolean'],
            'in_app' => ['boolean'],
        ]);

        $pref = NotificationPreference::on('pgsql_admin')->updateOrCreate(
            ['user_id' => $request->user()->id, 'category' => $data['category'], 'organization_id' => null, 'event_id' => null],
            [
                'email' => $data['email'] ?? true,
                'push' => $data['push'] ?? true,
                'sms' => $data['sms'] ?? false,
                'in_app' => $data['in_app'] ?? true,
            ],
        );

        return response()->json(['data' => [
            'category' => $pref->category,
            'email' => (bool) $pref->email,
            'push' => (bool) $pref->push,
            'sms' => (bool) $pref->sms,
            'in_app' => (bool) $pref->in_app,
        ]]);
    }
}
