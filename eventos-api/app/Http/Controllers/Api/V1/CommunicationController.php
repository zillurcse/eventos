<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Participation;
use App\Support\CommunicationCapabilities;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Participant-facing Communication → Functionality affordances.
 *
 * The attendee app uses this to hide create / like / comment / vote controls
 * that the organizer switched off for the caller's role. Writes are still
 * re-checked in FeedController / SessionEngagementController.
 */
class CommunicationController extends Controller
{
    /** GET /events/{event}/communication */
    public function show(Request $request): JsonResponse
    {
        $event = Event::with('settings')->findOrFail($request->attributes->get('event_id'));
        $participation = Participation::findOrFail($request->attributes->get('participation_id'));
        $communication = CommunicationCapabilities::communication($event);

        return response()->json([
            'data' => CommunicationCapabilities::forParticipant($participation, $communication),
        ]);
    }
}
