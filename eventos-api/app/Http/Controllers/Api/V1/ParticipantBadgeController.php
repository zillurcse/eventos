<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BadgeDesignResource;
use App\Models\Participation;
use App\Services\Badges\BadgeRenderData;
use App\Services\Badges\BadgeResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The attendee's own badge, for the event site's "My Badges" tab.
 *
 * Returns one badge per participation, not one per person: someone who is both
 * a speaker and an exhibitor's team member holds two passes at the same event
 * and needs whichever the door in front of them wants.
 *
 * Lives under `/my/` for the reason the surveys routes do — the organizer's
 * badge routes are `/events/{uuid}/badge-designs`, and identical URIs would
 * shadow each other, with Laravel handing both audiences to whichever was
 * declared first.
 */
class ParticipantBadgeController extends Controller
{
    public function index(Request $request, BadgeResolver $resolver, BadgeRenderData $renderData): JsonResponse
    {
        $participations = $this->participationsOf($request);

        $badges = $participations
            ->map(function (Participation $participation) use ($resolver, $renderData) {
                $design = $resolver->forParticipation($participation);

                // An event whose organizer never made a badge simply has none —
                // the page says so rather than drawing an empty rectangle.
                if (! $design) {
                    return null;
                }

                $data = $renderData->for($participation);

                return [
                    'participation_id' => $participation->uuid,
                    'role_label' => $data['role_label'],
                    'design' => new BadgeDesignResource($design),
                    'data' => $data,
                ];
            })
            ->filter()
            ->values();

        return response()->json(['data' => $badges]);
    }

    /**
     * Every participation this person holds at the event in context. The
     * participant middleware has already resolved one of them; the rest are
     * their other roles at the same event.
     *
     * @return \Illuminate\Support\Collection<int, Participation>
     */
    private function participationsOf(Request $request)
    {
        $eventId = (int) $request->attributes->get('event_id');
        $current = Participation::findOrFail((int) $request->attributes->get('participation_id'));

        return Participation::with(['contact', 'event.settings', 'event.coverFile'])
            ->where('event_id', $eventId)
            ->where('contact_id', $current->contact_id)
            ->orderBy('id')
            ->get();
    }
}
