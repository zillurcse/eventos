<?php

namespace App\Http\Middleware;

use App\Models\Contact;
use App\Models\Event;
use App\Models\Participation;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        abort_unless($participation, 403, 'You are not a participant in this event.');

        $this->tenant->set($event->organization_id);
        DB::statement("set app.current_organization = '{$event->organization_id}'");

        $request->attributes->set('event_id', $event->id);
        $request->attributes->set('participation_id', $participation->id);

        return $next($request);
    }
}
