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

        $form = Form::on('pgsql_admin')
            ->with('fields.options')
            ->where('event_id', $event->id)
            ->where('key', 'registration')
            ->where('status', 'published')
            ->latest('id')
            ->firstOrFail();

        $lines = $request->input('tickets', []);
        $discount = $request->input('discount_code');
        $password = $request->input('password');

        $result = $service->register(
            $event, $form, $request->except(['tickets', 'discount_code', 'password']), $lines, $discount, $password,
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
}
