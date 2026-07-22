<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ParticipationResource;
use App\Http\Resources\TicketResource;
use App\Models\Event;
use App\Models\Form;
use App\Services\Registration\RegistrationService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Public registration (no auth) — the event uuid + its published registration
 * form drive the flow. Loads via the migrator connection (public token), then
 * runs everything inside the event's own tenant context (architecture §6.4).
 */
class RegistrationController extends Controller
{
    public function __construct(protected TenantContext $tenant) {}

    public function register(string $eventUuid, Request $request, RegistrationService $service): JsonResponse
    {
        $event = Event::on('pgsql_admin')->where('uuid', $eventUuid)->firstOrFail();

        $this->tenant->set($event->organization_id);
        DB::statement("set app.current_organization = '{$event->organization_id}'");

        // A published event always accepts registration: use the organizer's
        // form if they built one, otherwise self-heal a default set so signup
        // works out of the box.
        $form = $this->resolveRegistrationForm($event);

        $lines = $request->input('tickets', []);
        $discount = $request->input('discount_code');
        $password = $request->input('password');

        // A profile form is collected per surface, so signup validates only the
        // fields it actually renders (Event Settings › Profile › "Add field to
        // user registration"). A legacy registration form has no surfaces and
        // keeps full validation.
        $only = $form->isProfileForm() ? $form->registrationKeys() : null;

        $result = $service->register(
            $event, $form, $request->except(['tickets', 'discount_code', 'password']), $lines, $discount, $password, $only,
        );

        return response()->json([
            'participation' => new ParticipationResource($result['participation']->load('contact')),
            'order' => $result['order'] ? [
                'number' => $result['order']->number,
                'status' => $result['order']->status,
                'subtotal_cents' => (int) $result['order']->subtotal_cents,
                'discount_cents' => (int) $result['order']->discount_cents,
                'total_cents' => (int) $result['order']->total_cents,
                'tickets' => TicketResource::collection($result['tickets']),
            ] : null,
        ], 201);
    }

    /**
     * The form signup collects through, in priority order:
     *   1. the published ATTENDEE PROFILE form (Event Settings › Profile) —
     *      where organizers now design registration fields;
     *   2. a legacy standalone `registration` form, for events built before
     *      the Profile section existed;
     *   3. a lazily-provisioned default set from config, so any published
     *      event can take signups without the organizer building anything.
     * Runs under the already-set tenant GUC, so RLS + org scoping apply.
     */
    protected function resolveRegistrationForm(Event $event): Form
    {
        $form = Form::on('pgsql_admin')
            ->with('fields.options')
            ->where('event_id', $event->id)
            ->whereIn('key', ['profile.attendee', 'registration'])
            ->where('status', 'published')
            ->orderByRaw("case when key = 'profile.attendee' then 0 else 1 end")
            ->orderByDesc('id')
            ->first();

        if ($form) {
            return $form;
        }

        $form = Form::create([
            'organization_id' => $event->organization_id,
            'event_id' => $event->id,
            'key' => 'registration',
            'name' => 'Registration',
            'target_entity' => 'participation',
            'status' => 'published',
            'version' => 1,
        ]);

        $sort = 0;
        foreach (config('eventos.default_fields.registration', []) as $f) {
            $form->fields()->create([
                'key' => $f['key'],
                'label' => $f['label'] ?? null,
                'type' => $f['type'],
                'is_default' => true,
                'is_required' => $f['is_required'] ?? false,
                'is_unique' => $f['is_unique'] ?? false,
                'is_pii' => $f['is_pii'] ?? false,
                'sort_order' => $sort++,
            ]);
        }

        return $form->load('fields.options');
    }
}
