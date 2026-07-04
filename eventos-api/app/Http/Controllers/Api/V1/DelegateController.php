<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Participation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Attendee-facing delegate directory ("Delegates" tab). Acts as the resolved
 * participation (ResolveParticipant middleware). Lists the event's fellow
 * attendees for networking — excludes the viewer, blocked people, and anyone
 * who opted out of networking. A connection request is a separate call
 * (ConnectionController@store).
 */
class DelegateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $eventId = $request->attributes->get('event_id');
        $me = $request->attributes->get('participation_id');

        $delegates = Participation::query()
            ->with('contact')
            ->select('participations.*')
            ->join('contacts', 'contacts.id', '=', 'participations.contact_id')
            ->where('participations.event_id', $eventId)
            ->where('participations.role', 'attendee')
            ->where('participations.id', '!=', $me)
            // Not blocked by the organizer.
            ->where(fn ($q) => $q->whereNull('participations.meta->blocked')->orWhere('participations.meta->blocked', false))
            // Discoverable: opted in, or hasn't made a choice (default visible).
            ->where(fn ($q) => $q->whereNull('participations.networking_opt_in')->orWhere('participations.networking_opt_in', true))
            ->orderByRaw("lower(coalesce(contacts.first_name,'')||' '||coalesce(contacts.last_name,''))")
            ->limit(500)
            ->get()
            ->map(fn (Participation $p) => $this->format($p))
            ->values();

        return response()->json(['data' => $delegates]);
    }

    private function format(Participation $p): array
    {
        $c = $p->contact;
        $meta = $p->meta ?? [];
        $profile = $p->profile_data ?? [];

        return [
            'id' => $p->uuid,
            'name' => $c ? (trim(($c->first_name ?? '').' '.($c->last_name ?? '')) ?: null) : null,
            'company' => $c?->company ?? ($profile['company'] ?? ''),
            'job_title' => $c?->job_title ?? ($profile['designation'] ?? ''),
            'avatar_url' => $meta['avatar_url'] ?? ($profile['avatar_url'] ?? ($profile['image_url'] ?? null)),
        ];
    }
}
