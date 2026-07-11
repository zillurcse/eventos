<?php

namespace App\Http\Middleware;

use App\Models\Contact;
use App\Models\Event;
use App\Models\Exhibitor;
use App\Models\ExhibitorMember;
use App\Models\Participation;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

/**
 * For attendee-facing routes (`/events/{event}/...`): resolves the signed-in
 * user's participation in the event and activates that event's org context
 * (architecture §6.5, §6.6). Identity reads run on the migrator connection.
 */
class ResolveParticipant
{
    public function __construct(protected TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user, 401);

        $event = Event::on('pgsql_admin')->where('uuid', $request->route('event'))->firstOrFail();

        $contactIds = Contact::on('pgsql_admin')->where('user_id', $user->id)->pluck('id');

        $participation = Participation::on('pgsql_admin')
            ->where('event_id', $event->id)
            ->whereIn('contact_id', $contactIds)
            ->first();

        // An exhibitor's team is created as contact + exhibitor_member with no
        // participation, so they could sign in to the event site but every
        // participant-scoped tab (meetings, delegates, feed) 403'd on them.
        // Staffing a booth is participating — enrol them on first use.
        $participation ??= $this->enrolExhibitorMember($event, $contactIds);

        abort_unless($participation, 403, 'You are not a participant in this event.');

        $this->tenant->set($event->organization_id);
        DB::statement("set app.current_organization = '{$event->organization_id}'");

        $request->attributes->set('event_id', $event->id);
        $request->attributes->set('organization_id', $event->organization_id);
        $request->attributes->set('participation_id', $participation->id);

        return $next($request);
    }

    /**
     * Enrol a member of one of this event's booths as a participant, so the
     * event site works for them like it does for anyone else. Returns null when
     * the user staffs no booth at this event — the ordinary 403 then applies.
     *
     * firstOrCreate keeps two concurrent requests (the SPA fires several tabs at
     * once on load) from racing a duplicate row in.
     */
    private function enrolExhibitorMember(Event $event, Collection $contactIds): ?Participation
    {
        if ($contactIds->isEmpty()) {
            return null;
        }

        $exhibitorIds = Exhibitor::on('pgsql_admin')->where('event_id', $event->id)->pluck('id');

        if ($exhibitorIds->isEmpty()) {
            return null;
        }

        $member = ExhibitorMember::on('pgsql_admin')
            ->whereIn('exhibitor_id', $exhibitorIds)
            ->whereIn('contact_id', $contactIds)
            ->first();

        if (! $member) {
            return null;
        }

        return Participation::on('pgsql_admin')->firstOrCreate(
            ['event_id' => $event->id, 'contact_id' => $member->contact_id],
            [
                'organization_id' => $event->organization_id,
                'role' => 'exhibitor',
                'status' => 'registered',
                'meta' => ['exhibitor_member_id' => $member->id],
            ],
        );
    }
}
