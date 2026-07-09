<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\NewExhibitorMessage;
use App\Http\Controllers\Controller;
use App\Models\ExhibitorConversation;
use App\Models\ExhibitorMeetingRequest;
use App\Models\ExhibitorMember;
use App\Models\ExhibitorMessage;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Exhibitor-side inbox for attendee "Contact" (Chat + Meet). The active
 * exhibitor + acting member are resolved by ResolveExhibitorAdmin. The team
 * reads incoming messages and replies, and assigns a member to meeting
 * requests (which confirms them and notifies the attendee back).
 */
class ExhibitorInboxController extends Controller
{
    /** GET /exhibitor/inbox/conversations — every attendee thread for this booth. */
    public function conversations(Request $request): JsonResponse
    {
        $exhibitorId = (int) $request->attributes->get('exhibitor_id');

        $conversations = ExhibitorConversation::with(['participation.contact', 'assignedMember.contact'])
            ->where('exhibitor_id', $exhibitorId)
            ->withCount(['messages as unread_count' => fn ($q) => $q
                ->where('sender_side', 'attendee')->whereNull('read_at')])
            ->orderByDesc('last_message_at')
            ->get();

        $last = ExhibitorMessage::whereIn('conversation_id', $conversations->pluck('id'))
            ->orderByDesc('id')->get()->unique('conversation_id')->keyBy('conversation_id');

        return response()->json(['data' => $conversations->map(function (ExhibitorConversation $c) use ($last) {
            $m = $last->get($c->id);

            return [
                'id' => $c->uuid,
                'attendee' => $this->attendee($c),
                'assigned_to' => $this->memberName($c->assignedMember),
                'unread' => (int) $c->unread_count,
                'last_message' => $m ? [
                    'body' => mb_strimwidth($m->body, 0, 120, '…'),
                    'mine' => $m->sender_side === 'exhibitor',
                    'created_at' => $m->created_at?->toIso8601String(),
                ] : null,
            ];
        })->values()]);
    }

    /** GET /exhibitor/inbox/conversations/{conversation}/messages — thread history. */
    public function messages(Request $request, string $conversation): JsonResponse
    {
        $convo = $this->resolveConversation($request, $conversation);

        // Mark the attendee's messages read now the team is viewing them.
        $convo->messages()->where('sender_side', 'attendee')->whereNull('read_at')->update(['read_at' => now()]);

        $messages = $convo->messages()->orderBy('id')->get()
            ->map(fn (ExhibitorMessage $m) => $this->formatMessage($m))->values();

        return response()->json(['data' => [
            'attendee' => $this->attendee($convo->load('participation.contact')),
            'messages' => $messages,
        ]]);
    }

    /** POST /exhibitor/inbox/conversations/{conversation}/messages — reply. */
    public function reply(Request $request, string $conversation, NotificationService $notifications): JsonResponse
    {
        $convo = $this->resolveConversation($request, $conversation);
        $memberId = (int) $request->attributes->get('exhibitor_member_id');

        $data = $request->validate(['body' => ['required', 'string', 'max:1000']]);

        $message = $convo->messages()->create([
            'organization_id' => $convo->organization_id,
            'event_id' => $convo->event_id,
            'sender_side' => 'exhibitor',
            'sender_member_id' => $memberId,
            'body' => $data['body'],
        ]);

        $convo->update(['last_message_at' => now()]);

        // Live delivery to the attendee (Reverb); the exhibitor SPA polls.
        broadcast(new NewExhibitorMessage(
            $message,
            $convo->uuid,
            $convo->exhibitor->uuid ?? '',
            (int) $convo->participation_id,
        ));

        $notifications->notify(
            'participation', (int) $convo->participation_id, $convo->organization_id, $convo->event_id,
            'exhibitor.message_reply',
            ['title' => 'New reply', 'body' => $this->bookName($convo).' replied to your message.', 'exhibitor_id' => $convo->exhibitor->uuid ?? null],
        );

        return response()->json(['data' => $this->formatMessage($message)], 201);
    }

    /** GET /exhibitor/inbox/meeting-requests — requests attendees sent this booth. */
    public function meetingRequests(Request $request): JsonResponse
    {
        $exhibitorId = (int) $request->attributes->get('exhibitor_id');

        $requests = ExhibitorMeetingRequest::with(['participation.contact', 'assignedMember.contact'])
            ->where('exhibitor_id', $exhibitorId)
            ->latest('id')
            ->get()
            ->map(fn (ExhibitorMeetingRequest $r) => [
                'id' => $r->uuid,
                'status' => $r->status,
                'subject' => $r->subject,
                'agenda' => $r->agenda,
                'starts_at' => $r->starts_at?->toIso8601String(),
                'ends_at' => $r->ends_at?->toIso8601String(),
                'date' => $r->meta['lounge_date'] ?? null,
                'slot' => $r->meta['lounge_slot'] ?? null,
                'attendee' => $this->attendee($r),
                'assigned_to' => $this->memberName($r->assignedMember),
                'assigned_member_id' => $r->assigned_member_id,
                'created_at' => $r->created_at?->toIso8601String(),
            ])->values();

        return response()->json(['data' => $requests]);
    }

    /**
     * PATCH /exhibitor/inbox/meeting-requests/{request} — assign a member
     * (confirms the meeting) or decline. Notifies the attendee either way.
     */
    public function respondMeeting(Request $request, string $request_uuid, NotificationService $notifications): JsonResponse
    {
        $exhibitorId = (int) $request->attributes->get('exhibitor_id');

        $data = $request->validate([
            'action' => ['required', 'in:assign,decline'],
            'member_id' => ['required_if:action,assign', 'nullable', 'integer'],
        ]);

        $req = ExhibitorMeetingRequest::with('exhibitor')
            ->where('exhibitor_id', $exhibitorId)
            ->where('uuid', $request_uuid)
            ->firstOrFail();

        if ($data['action'] === 'decline') {
            $req->update(['status' => 'declined', 'responded_at' => now()]);

            $notifications->notify(
                'participation', (int) $req->participation_id, $req->organization_id, $req->event_id,
                'exhibitor.meeting_declined',
                ['title' => 'Meeting declined', 'body' => $this->bookName($req).' declined your meeting request.'],
            );

            return response()->json(['data' => $this->requestPayload($req->fresh(['assignedMember.contact', 'participation.contact']))]);
        }

        // Assign — the member must belong to this exhibitor.
        $member = ExhibitorMember::with('contact')
            ->where('exhibitor_id', $exhibitorId)
            ->findOrFail($data['member_id']);

        $req->update([
            'assigned_member_id' => $member->id,
            'status' => 'confirmed',
            'responded_at' => now(),
        ]);

        $notifications->notify(
            'participation', (int) $req->participation_id, $req->organization_id, $req->event_id,
            'exhibitor.meeting_confirmed',
            ['title' => 'Meeting confirmed', 'body' => $this->bookName($req).' confirmed your meeting with '.$this->memberName($member).'.'],
        );

        return response()->json(['data' => $this->requestPayload($req->fresh(['assignedMember.contact', 'participation.contact']))]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function resolveConversation(Request $request, string $uuid): ExhibitorConversation
    {
        return ExhibitorConversation::with('exhibitor')
            ->where('exhibitor_id', (int) $request->attributes->get('exhibitor_id'))
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    private function attendee($model): array
    {
        $p = $model->participation;
        $c = $p?->contact;
        $profile = $p?->profile_data ?? [];

        return [
            'name' => trim(($c->first_name ?? '').' '.($c->last_name ?? '')) ?: 'Attendee',
            'company' => $c->company ?? ($profile['company'] ?? ''),
            'job_title' => $c->job_title ?? ($profile['designation'] ?? ''),
        ];
    }

    private function memberName(?ExhibitorMember $m): ?string
    {
        if (! $m) {
            return null;
        }
        $c = $m->contact;

        return trim(($c->first_name ?? '').' '.($c->last_name ?? '')) ?: ($c->email ?? 'Member');
    }

    private function bookName($model): string
    {
        return $model->exhibitor->name ?? 'The exhibitor';
    }

    private function formatMessage(ExhibitorMessage $m): array
    {
        return [
            'id' => $m->uuid,
            'body' => $m->body,
            'side' => $m->sender_side,
            // From the exhibitor team's view, "mine" is the exhibitor side.
            'mine' => $m->sender_side === 'exhibitor',
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }

    private function requestPayload(ExhibitorMeetingRequest $r): array
    {
        return [
            'id' => $r->uuid,
            'status' => $r->status,
            'subject' => $r->subject,
            'agenda' => $r->agenda,
            'date' => $r->meta['lounge_date'] ?? null,
            'slot' => $r->meta['lounge_slot'] ?? null,
            'attendee' => $this->attendee($r),
            'assigned_to' => $this->memberName($r->assignedMember),
            'assigned_member_id' => $r->assigned_member_id,
            'created_at' => $r->created_at?->toIso8601String(),
        ];
    }
}
