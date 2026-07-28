<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Gamification;
use App\Models\Participation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Attendee-facing gamification: leaderboard + award block + caller's score.
 */
class ParticipantGamificationController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $eventId = (int) $request->attributes->get('event_id');
        $pid = (int) $request->attributes->get('participation_id');

        $config = Gamification::where('event_id', $eventId)->first();
        $me = Participation::with('contact')->find($pid);

        $leaders = Participation::with('contact')
            ->where('event_id', $eventId)
            ->where('points_total', '>', 0)
            ->orderByDesc('points_total')
            ->orderBy('id')
            ->limit(20)
            ->get()
            ->map(function (Participation $p, int $i) use ($pid) {
                $profile = is_array($p->profile_data) ? $p->profile_data : [];
                $role = trim((string) (
                    $profile['job_title']
                    ?? $profile['designation']
                    ?? ''
                ));

                return [
                    'rank' => $i + 1,
                    'name' => $p->contact?->fullName() ?: 'Participant',
                    'role' => $role !== '' ? $role : null,
                    'avatar_url' => $profile['avatar_url'] ?? $profile['image_url'] ?? null,
                    'points' => (int) $p->points_total,
                    'is_me' => $p->id === $pid,
                ];
            })
            ->values();

        return response()->json([
            'data' => [
                'enabled' => (bool) ($config?->enabled),
                'award_title' => $config?->award_title,
                'award_description' => $config?->award_description,
                'my_points' => (int) ($me?->points_total ?? 0),
                'leaderboard' => $leaders,
            ],
        ]);
    }
}
