<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\NormalizesTimestamps;
use App\Http\Controllers\Controller;
use App\Models\Exhibitor;
use App\Models\ExhibitorConversation;
use App\Models\ExhibitorMeetingRequest;
use App\Models\ExhibitorMember;
use App\Models\ExhibitorMessage;
use App\Models\Participation;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Attendee → exhibitor "Contact" (Chat + Meet). The attendee is the resolved
 * participation (ResolveParticipant middleware); the recipient is an exhibitor
 * company whose admin later assigns a member. Phase 1: attendee can open a
 * thread, send a message, and request a meeting; the exhibitor's admin members
 * are notified. Assignment + replies land in the exhibitor-admin inbox (phase 2).
 */
class ExhibitorContactController extends Controller
{
    use NormalizesTimestamps;

    /** GET /events/{event}/exhibitors/{exhibitor}/thread — my chat with this booth. */
    public function thread(Request $request, string $event, string $exhibitor): JsonResponse
    {
        $me = (int) $request->attributes->get('participation_id');
        $exh = $this->resolveExhibitor($request, $exhibitor);

        $convo = ExhibitorConversation::with(['messages' => fn ($q) => $q->orderBy('id')])
            ->where('exhibitor_id', $exh->id)
            ->where('participation_id', $me)
            ->first();

        // Mark the exhibitor-side messages read now that the attendee is viewing.
        if ($convo) {
            $convo->messages()->where('sender_side', 'exhibitor')->whereNull('read_at')->update(['read_at' => now()]);
        }

        return response()->json([
            'data' => [
                // My participation uuid = my personal Reverb channel key.
                'me' => Participation::find($me)?->uuid,
                'exhibitor' => ['id' => $exh->uuid, 'name' => $exh->name],
                'conversation_id' => $convo?->uuid,
                'messages' => $convo
                    ? $convo->messages->map(fn (ExhibitorMessage $m) => $this->formatMessage($m))->values()
                    : [],
            ],
        ]);
    }

    /** GET /events/{event}/exhibitor-conversations — my threads across all booths. */
    public function conversations(Request $request, string $event): JsonResponse
    {
        $me = (int) $request->attributes->get('participation_id');

        $convos = ExhibitorConversation::with('exhibitor')
            ->where('participation_id', $me)
            ->withCount(['messages as unread_count' => fn ($q) => $q
                ->where('sender_side', 'exhibitor')->whereNull('read_at')])
            ->orderByDesc('last_message_at')
            ->get();

        $last = ExhibitorMessage::whereIn('conversation_id', $convos->pluck('id'))
            ->orderByDesc('id')->get()->unique('conversation_id')->keyBy('conversation_id');

        return response()->json(['data' => $convos->map(function (ExhibitorConversation $c) use ($last) {
            $m = $last->get($c->id);

            return [
                'id' => $c->uuid,
                'exhibitor_id' => $c->exhibitor->uuid ?? null,
                'name' => $c->exhibitor->name ?? 'Exhibitor',
                'unread' => (int) $c->unread_count,
                'last_message' => $m ? [
                    'body' => mb_strimwidth($m->body, 0, 120, '…'),
                    'mine' => $m->sender_side === 'attendee',
                    'created_at' => $m->created_at?->toIso8601String(),
                ] : null,
            ];
        })->values()]);
    }

    /** POST /events/{event}/exhibitors/{exhibitor}/messages — send a message. */
    public function sendMessage(Request $request, string $event, string $exhibitor, NotificationService $notifications): JsonResponse
    {
        $me = (int) $request->attributes->get('participation_id');
        $eventId = (int) $request->attributes->get('event_id');
        $orgId = (int) $request->attributes->get('organization_id');
        $exh = $this->resolveExhibitor($request, $exhibitor);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
        ]);

        $convo = ExhibitorConversation::firstOrCreate(
            ['event_id' => $eventId, 'exhibitor_id' => $exh->id, 'participation_id' => $me],
            ['organization_id' => $orgId],
        );

        $message = $convo->messages()->create([
            'organization_id' => $orgId,
            'event_id' => $eventId,
            'sender_side' => 'attendee',
            'sender_participation_id' => $me,
            'body' => $data['body'],
        ]);

        $convo->update(['last_message_at' => now()]);

        $this->notifyAdmins(
            $notifications, $exh, $orgId, $eventId,
            'exhibitor.message',
            'New message',
            $this->attendeeName($me).' sent you a message.',
            ['conversation_id' => $convo->uuid],
        );

        return response()->json(['data' => [
            'conversation_id' => $convo->uuid,
            'message' => $this->formatMessage($message),
        ]], 201);
    }

    /** GET /events/{event}/exhibitors/{exhibitor}/meeting-requests — my requests. */
    public function meetingRequests(Request $request, string $event, string $exhibitor): JsonResponse
    {
        $me = (int) $request->attributes->get('participation_id');
        $exh = $this->resolveExhibitor($request, $exhibitor);

        $requests = ExhibitorMeetingRequest::with('assignedMember.contact')
            ->where('exhibitor_id', $exh->id)
            ->where('participation_id', $me)
            ->latest('id')
            ->get()
            ->map(fn (ExhibitorMeetingRequest $r) => $this->formatRequest($r))
            ->values();

        return response()->json(['data' => $requests]);
    }

    /** POST /events/{event}/exhibitors/{exhibitor}/meeting-requests — request a meeting. */
    public function requestMeeting(Request $request, string $event, string $exhibitor, NotificationService $notifications): JsonResponse
    {
        $me = (int) $request->attributes->get('participation_id');
        $eventId = (int) $request->attributes->get('event_id');
        $orgId = (int) $request->attributes->get('organization_id');
        $exh = $this->resolveExhibitor($request, $exhibitor);

        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:200'],
            'agenda' => ['nullable', 'string', 'max:1000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'date' => ['nullable', 'date_format:Y-m-d', 'required_with:slot'],
            'slot' => ['nullable', 'regex:/^\d{2}:\d{2}-\d{2}:\d{2}$/', 'required_with:date'],
        ]);

        $data = $this->utcDates($data, ['starts_at', 'ends_at']);

        $meta = null;
        if (! empty($data['date']) && ! empty($data['slot'])) {
            $meta = ['lounge_date' => $data['date'], 'lounge_slot' => $data['slot']];
        }

        $req = ExhibitorMeetingRequest::create([
            'organization_id' => $orgId,
            'event_id' => $eventId,
            'exhibitor_id' => $exh->id,
            'participation_id' => $me,
            'status' => 'requested',
            'subject' => $data['subject'] ?? null,
            'agenda' => $data['agenda'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'meta' => $meta,
        ]);

        $this->notifyAdmins(
            $notifications, $exh, $orgId, $eventId,
            'exhibitor.meeting_requested',
            'New meeting request',
            $this->attendeeName($me).' requested a meeting.',
            ['meeting_request_id' => $req->uuid],
        );

        return response()->json(['data' => $this->formatRequest($req)], 201);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function resolveExhibitor(Request $request, string $uuid): Exhibitor
    {
        $eventId = (int) $request->attributes->get('event_id');

        return Exhibitor::where('event_id', $eventId)
            ->where('uuid', $uuid)
            ->where('status', 'active')
            ->firstOrFail();
    }

    /** Notify every admin/staff member of the exhibitor (in-app, per contact). */
    private function notifyAdmins(
        NotificationService $notifications,
        Exhibitor $exh,
        int $orgId,
        int $eventId,
        string $key,
        string $title,
        string $body,
        array $extra = [],
    ): void {
        $members = ExhibitorMember::where('exhibitor_id', $exh->id)
            ->whereIn('role', ['admin', 'staff'])
            ->whereNotNull('contact_id')
            ->get();

        foreach ($members as $member) {
            $notifications->notify(
                'contact', (int) $member->contact_id, $orgId, $eventId,
                $key,
                array_merge(['title' => $title, 'body' => $body, 'exhibitor_id' => $exh->uuid], $extra),
            );
        }
    }

    private function attendeeName(int $participationId): string
    {
        $p = Participation::with('contact')->find($participationId);
        $c = $p?->contact;

        return trim(($c->first_name ?? '').' '.($c->last_name ?? '')) ?: 'An attendee';
    }

    private function formatMessage(ExhibitorMessage $m): array
    {
        return [
            'id' => $m->uuid,
            'body' => $m->body,
            'side' => $m->sender_side,
            'mine' => $m->sender_side === 'attendee',
            'read_at' => $m->read_at?->toIso8601String(),
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }

    private function formatRequest(ExhibitorMeetingRequest $r): array
    {
        $member = $r->relationLoaded('assignedMember') ? $r->assignedMember : null;
        $mc = $member?->contact;

        return [
            'id' => $r->uuid,
            'status' => $r->status,
            'subject' => $r->subject,
            'agenda' => $r->agenda,
            'starts_at' => $r->starts_at?->toIso8601String(),
            'ends_at' => $r->ends_at?->toIso8601String(),
            'date' => $r->meta['lounge_date'] ?? null,
            'slot' => $r->meta['lounge_slot'] ?? null,
            'assigned_to' => $mc ? trim(($mc->first_name ?? '').' '.($mc->last_name ?? '')) : null,
            'created_at' => $r->created_at?->toIso8601String(),
        ];
    }
}
