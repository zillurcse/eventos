<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BadgeDesignResource;
use App\Models\BadgeDesign;
use App\Models\Contact;
use App\Models\Event;
use App\Models\Participation;
use App\Models\ParticipationGroup;
use App\Services\Badges\BadgeRenderData;
use App\Services\Badges\BadgeTemplateFactory;
use App\Support\BadgeAudience;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Guest badges — passes issued to people the event never registered: press,
 * VVIPs, a minister's staff, a partner's delegation. The organizer uploads a
 * spreadsheet of names, picks a design, and prints.
 *
 * ── Why a "batch" is a participation group ───────────────────────────────────
 * A batch is the unit an organizer actually works in: "the 50 media passes for
 * day one". That is precisely a named set of people at an event, which is what
 * `participation_groups` already is — with organization_id, RLS and the rest of
 * the product (targeted ads, notifications) already able to address one. So a
 * batch is a group with `type='guest_badge'`, its config in `meta`.
 *
 * ── Why guests are real participations ───────────────────────────────────────
 * A guest badge has a QR on it, and that QR has to work at the gate. Making
 * guests `participations` (role=guest) means CheckInController, gate analytics
 * and the exhibitor scanners all handle them on day one with no special case —
 * the alternative, a parallel `guest_badges` table, would have needed every one
 * of those to learn about a second kind of person.
 *
 * The design is pinned onto each guest (`meta.badge_design_id`) rather than
 * inferred, so moving a batch onto a new design is an explicit re-assignment
 * and a guest already printed never silently changes badge.
 */
class GuestBadgeController extends Controller
{
    private const GROUP_TYPE = 'guest_badge';

    /** Columns a guest row may carry, in the order the sample file lists them. */
    public const IMPORT_COLUMNS = ['full_name', 'designation', 'company', 'email', 'phone', 'photo_url'];

    /** Every guest-badge batch of this event, with its design and headcount. */
    public function index(string $uuid): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $batches = ParticipationGroup::where('event_id', $event->id)
            ->where('type', self::GROUP_TYPE)
            ->orderByDesc('id')
            ->get();

        $designs = BadgeDesign::where('event_id', $event->id)->get()->keyBy('id');

        $counts = DB::table('participation_group_member')
            ->whereIn('group_id', $batches->pluck('id'))
            ->select('group_id', DB::raw('count(*) as total'))
            ->groupBy('group_id')
            ->pluck('total', 'group_id');

        return response()->json([
            'data' => $batches->map(fn (ParticipationGroup $b) => $this->batchPayload(
                $b,
                $designs->get($b->meta['badge_design_id'] ?? 0),
                (int) ($counts[$b->id] ?? 0),
            )),
        ]);
    }

    /**
     * Step 1 of the wizard. Creating a batch also creates the design it will
     * print on — a guest badge with no design is not a thing an organizer can
     * do anything with, and the starter template is immediately editable.
     */
    public function store(Request $request, string $uuid, BadgeTemplateFactory $factory): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'guest_type' => ['required', 'string', 'max:60'],
            // Reuse an existing guest design instead of generating one.
            'badge_design_id' => ['nullable', 'integer'],
        ]);

        return DB::transaction(function () use ($event, $data, $factory, $request) {
            $design = null;

            if (! empty($data['badge_design_id'])) {
                $design = BadgeDesign::where('event_id', $event->id)
                    ->where('id', $data['badge_design_id'])
                    ->firstOrFail();
            }

            $design ??= BadgeDesign::create([
                ...$factory->build(BadgeAudience::Guest, $data['guest_type'], $data['name']),
                'event_id' => $event->id,
                'is_default' => false,
                'created_by' => $request->user()?->id,
            ]);

            $batch = ParticipationGroup::create([
                'event_id' => $event->id,
                'name' => $data['name'],
                'type' => self::GROUP_TYPE,
                'meta' => [
                    'guest_type' => $data['guest_type'],
                    'badge_design_id' => $design->id,
                    'delivery' => null,
                ],
            ]);

            return response()->json(['data' => $this->batchPayload($batch, $design, 0)], 201);
        });
    }

    /** One batch, with its guest list — what the wizard's later steps read. */
    public function show(int $batch, BadgeRenderData $renderData): JsonResponse
    {
        $model = $this->findBatch($batch);
        $design = $this->designOf($model);

        $guests = $this->guestsOf($model);

        return response()->json([
            'data' => [
                ...$this->batchPayload($model, $design, $guests->count()),
                'guests' => $guests->map(fn (Participation $p) => [
                    'id' => $p->uuid,
                    'full_name' => $p->contact?->fullName() ?: '',
                    'email' => $p->contact?->email ?: '',
                    'designation' => $p->profile_data['designation'] ?? '',
                    'company' => $p->profile_data['company'] ?? '',
                    'status' => $p->status,
                    // Everything the badge needs, so print is a pure client-side
                    // render with no second round-trip per guest.
                    'render' => $renderData->for($p),
                ])->values(),
            ],
        ]);
    }

    /**
     * Step 2 of the wizard: commit the uploaded guest list.
     *
     * Rows are matched to contacts by email, which is how contacts are keyed
     * org-wide — so importing the same press list twice updates those people
     * rather than duplicating them. Rows without an email are still accepted
     * (plenty of guest lists are just names), but they can only ever create a
     * new contact, never match an existing one.
     */
    public function importGuests(Request $request, int $batch): JsonResponse
    {
        $model = $this->findBatch($batch);

        $data = $request->validate([
            'guests' => ['required', 'array', 'min:1', 'max:500'],
            'guests.*.full_name' => ['required', 'string', 'max:180'],
            'guests.*.email' => ['nullable', 'email', 'max:190'],
            'guests.*.designation' => ['nullable', 'string', 'max:180'],
            'guests.*.company' => ['nullable', 'string', 'max:180'],
            'guests.*.phone' => ['nullable', 'string', 'max:32'],
            'guests.*.photo_url' => ['nullable', 'string', 'max:2048'],
        ]);

        $guestType = $model->meta['guest_type'] ?? 'Guest';
        $designId = $model->meta['badge_design_id'] ?? null;

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($data, $model, $guestType, $designId, &$created, &$updated) {
            foreach ($data['guests'] as $row) {
                [$first, $last] = $this->splitName($row['full_name']);

                $contact = ! empty($row['email'])
                    ? Contact::firstOrCreate(
                        ['email' => $row['email']],
                        ['first_name' => $first, 'last_name' => $last],
                    )
                    : Contact::create([
                        'first_name' => $first,
                        'last_name' => $last,
                        // `contacts.email` is NOT NULL and contacts are keyed by
                        // it, but plenty of guest lists are names on a door
                        // sheet with no address at all. Mint an unroutable one
                        // in the reserved `.invalid` TLD (RFC 2606) so the row
                        // is valid and unique while never being deliverable —
                        // and flag it, so nothing later mistakes it for a real
                        // address to mail.
                        'email' => 'guest-'.Str::uuid().'@guests.invalid',
                        'meta' => ['email_placeholder' => true],
                    ]);

                // Scoped to role=guest deliberately. Participations are unique
                // on (event_id, contact_id, role), so a press guest who also
                // registered as an attendee gets a second, separate
                // participation — matching on the pair alone would have
                // rewritten their attendee row into a guest one and taken their
                // event access with it.
                $participation = Participation::where('event_id', $model->event_id)
                    ->where('contact_id', $contact->id)
                    ->where('role', 'guest')
                    ->first()
                    ?? new Participation(['event_id' => $model->event_id, 'contact_id' => $contact->id]);

                $participation->exists ? $updated++ : $created++;

                $participation->fill([
                    'status' => 'confirmed',
                    'profile_data' => [
                        ...($participation->profile_data ?? []),
                        'designation' => $row['designation'] ?? '',
                        'company' => $row['company'] ?? '',
                        'phone' => $row['phone'] ?? '',
                        'image_url' => $row['photo_url'] ?? '',
                    ],
                    'meta' => [
                        ...($participation->meta ?? []),
                        'guest_type' => $guestType,
                        'badge_design_id' => $designId,
                        'guest_batch_id' => $model->id,
                    ],
                ]);
                // role is privileged (not $fillable) — set at trusted sites only.
                $participation->forceFill(['role' => 'guest'])->save();

                DB::table('participation_group_member')->updateOrInsert([
                    'group_id' => $model->id,
                    'participation_id' => $participation->id,
                ]);
            }
        });

        return response()->json([
            'meta' => ['created' => $created, 'updated' => $updated, 'total' => $created + $updated],
        ], 201);
    }

    /**
     * Step 4: record how the batch went out. The badges themselves are rendered
     * by the client (it already has every guest's `render` payload from show(),
     * and the canvas renderer lives there) — what the server owns is the fact
     * that this batch was delivered, so a reprint is a deliberate second act.
     */
    public function deliver(Request $request, int $batch): JsonResponse
    {
        $model = $this->findBatch($batch);

        $data = $request->validate([
            'method' => ['required', 'in:print,email,qr'],
        ]);

        $model->update([
            'meta' => [
                ...($model->meta ?? []),
                'delivery' => [
                    'method' => $data['method'],
                    'at' => now()->toIso8601String(),
                    'by' => $request->user()?->id,
                ],
            ],
        ]);

        return response()->json([
            'data' => $this->batchPayload($model, $this->designOf($model), $this->guestsOf($model)->count()),
        ]);
    }

    /**
     * Delete a batch and the guests it created. Guests are only removed when
     * this batch is the one that made them (`meta.guest_batch_id`) — someone who
     * registered normally and was later added to a press list keeps their
     * participation, and their event access with it.
     */
    public function destroy(int $batch): JsonResponse
    {
        $model = $this->findBatch($batch);

        DB::transaction(function () use ($model) {
            $this->guestsOf($model)
                ->filter(fn (Participation $p) => ($p->meta['guest_batch_id'] ?? null) === $model->id)
                ->each->delete();

            DB::table('participation_group_member')->where('group_id', $model->id)->delete();
            $model->delete();
        });

        return response()->json(['status' => 'success']);
    }

    // ── Internals ────────────────────────────────────────────────────────────

    /**
     * Resolved by hand rather than by route binding: the tenant GUC is already
     * set by the time we get here, and binding at that point would run the query
     * before RLS could scope it. Same reason BadgeDesignController does it.
     */
    private function findBatch(int $id): ParticipationGroup
    {
        return ParticipationGroup::where('type', self::GROUP_TYPE)->findOrFail($id);
    }

    private function designOf(ParticipationGroup $batch): ?BadgeDesign
    {
        $id = $batch->meta['badge_design_id'] ?? null;

        return $id ? BadgeDesign::find($id) : null;
    }

    /** @return \Illuminate\Support\Collection<int, Participation> */
    private function guestsOf(ParticipationGroup $batch)
    {
        return Participation::with(['contact', 'event.settings'])
            ->whereIn('id', DB::table('participation_group_member')
                ->where('group_id', $batch->id)
                ->pluck('participation_id'))
            ->orderBy('id')
            ->get();
    }

    /** @return array<string, mixed> */
    private function batchPayload(ParticipationGroup $batch, ?BadgeDesign $design, int $guestCount): array
    {
        return [
            'id' => $batch->id,
            'name' => $batch->name,
            'guest_type' => $batch->meta['guest_type'] ?? null,
            'guest_count' => $guestCount,
            'delivery' => $batch->meta['delivery'] ?? null,
            'design' => $design ? new BadgeDesignResource($design) : null,
            'created_at' => $batch->created_at,
        ];
    }

    /** @return array{0: string, 1: string} */
    private function splitName(string $full): array
    {
        $parts = preg_split('/\s+/', trim($full), 2);

        return [$parts[0] ?? $full, $parts[1] ?? ''];
    }
}
