<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\NewChatMessage;
use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Event;
use App\Models\Participation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * One-to-one participant chat ("Chat" in the event app). Acts as the resolved
 * participation (ResolveParticipant middleware). A conversation is a
 * normalized participation pair (a < b); who may START a chat with whom is
 * governed by the per-event role matrix in event_settings.chat
 * (Communication → Chat in the admin) — replies in an existing thread are
 * always allowed. Messages fan out live over Reverb (NewChatMessage).
 */
class ChatController extends Controller
{
    /** Participation roles that can appear in the chat directory. */
    private const CHAT_ROLES = ['attendee', 'speaker', 'exhibitor', 'sponsor'];

    /** The signed-in participant's inbox: threads, counterparts, unread. */
    public function index(Request $request): JsonResponse
    {
        $me = (int) $request->attributes->get('participation_id');

        $conversations = ChatConversation::where(function ($q) use ($me) {
            $q->where('a_participation_id', $me)->orWhere('b_participation_id', $me);
        })
            ->with(['a.contact', 'b.contact'])
            ->withCount(['messages as unread_count' => fn ($q) => $q
                ->where('sender_participation_id', '!=', $me)
                ->whereNull('read_at')])
            ->orderByDesc('last_message_at')
            ->limit(200)
            ->get();

        // One query for every thread's latest message (preview line).
        $lastMessages = ChatMessage::whereIn('conversation_id', $conversations->pluck('id'))
            ->orderByDesc('id')
            ->get()
            ->unique('conversation_id')
            ->keyBy('conversation_id');

        $meP = Participation::with('contact')->find($me);

        return response()->json([
            'me' => $meP?->uuid,
            // My own display card — the thread view renders name+avatar above
            // every message (EXPOUSE style), including my own.
            'profile' => $meP ? $this->person($meP) : null,
            'data' => $conversations->map(function (ChatConversation $c) use ($me, $lastMessages) {
                $other = $c->a_participation_id === $me ? $c->b : $c->a;
                $last = $lastMessages->get($c->id);

                return [
                    'id' => $c->uuid,
                    'with' => $this->person($other),
                    'unread' => (int) $c->unread_count,
                    'last_message' => $last ? [
                        'body' => mb_strimwidth(self::previewLabel($last), 0, 120, '…'),
                        'mine' => $last->sender_participation_id === $me,
                        'created_at' => $last->created_at?->toIso8601String(),
                    ] : null,
                ];
            })->values(),
        ]);
    }

    /**
     * Directory of people the current participant may start a chat with —
     * fellow attendees, speakers, exhibitors, sponsors — honoring the
     * event's chat permission matrix, block flags and networking opt-out.
     */
    public function partners(Request $request): JsonResponse
    {
        $eventId = $request->attributes->get('event_id');
        $me = (int) $request->attributes->get('participation_id');

        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', 'in:attendee,speaker,exhibitor,sponsor'],
        ]);

        $allowed = $this->allowedTargetRoles($request);
        if (! empty($data['role'])) {
            $allowed = array_values(array_intersect($allowed, [$data['role']]));
        }
        if (! $allowed) {
            return response()->json(['data' => [], 'roles' => []]);
        }

        $query = Participation::query()
            ->with('contact')
            ->select('participations.*')
            ->join('contacts', 'contacts.id', '=', 'participations.contact_id')
            ->where('participations.event_id', $eventId)
            ->whereIn('participations.role', $allowed)
            ->where('participations.id', '!=', $me)
            ->where(fn ($q) => $q->whereNull('participations.meta->blocked')->orWhere('participations.meta->blocked', false))
            ->where(fn ($q) => $q->whereNull('participations.networking_opt_in')->orWhere('participations.networking_opt_in', true));

        if (! empty($data['q'])) {
            $term = '%'.$data['q'].'%';
            $query->where(fn ($q) => $q
                ->whereRaw("coalesce(contacts.first_name,'')||' '||coalesce(contacts.last_name,'') ilike ?", [$term])
                ->orWhere('contacts.company', 'ilike', $term));
        }

        $people = $query
            ->orderByRaw("lower(coalesce(contacts.first_name,'')||' '||coalesce(contacts.last_name,''))")
            ->limit(100)
            ->get()
            ->map(fn (Participation $p) => $this->person($p))
            ->values();

        return response()->json(['data' => $people, 'roles' => $this->allowedTargetRoles($request)]);
    }

    /** Find-or-create the 1:1 thread with another participant. */
    public function open(Request $request): JsonResponse
    {
        $eventId = $request->attributes->get('event_id');
        $me = (int) $request->attributes->get('participation_id');

        $data = $request->validate(['participant' => ['required', 'uuid']]);

        $other = Participation::where('uuid', $data['participant'])
            ->where('event_id', $eventId)
            ->firstOrFail();
        abort_if($other->id === $me, 422, 'You cannot chat with yourself.');
        abort_if((bool) data_get($other->meta, 'blocked', false), 403, 'This person is not available.');

        [$a, $b] = $me < $other->id ? [$me, $other->id] : [$other->id, $me];
        $conversation = ChatConversation::where('event_id', $eventId)
            ->where('a_participation_id', $a)->where('b_participation_id', $b)
            ->first();

        // The matrix only gates STARTING a conversation; existing threads stay open.
        if (! $conversation) {
            abort_unless(
                in_array($other->role ?? 'attendee', $this->allowedTargetRoles($request), true),
                403,
                'The organizer has not enabled chat with this role.',
            );

            $conversation = ChatConversation::create([
                'event_id' => $eventId,
                'a_participation_id' => $a,
                'b_participation_id' => $b,
            ]);
        }

        return response()->json(['data' => [
            'id' => $conversation->uuid,
            'with' => $this->person($other),
            'unread' => 0,
        ]], 201);
    }

    /** Thread history (paginated, oldest-first page) + marks incoming read. */
    public function messages(string $event, string $conversation, Request $request): JsonResponse
    {
        $me = (int) $request->attributes->get('participation_id');
        $thread = $this->resolveConversation($request, $conversation, $me);

        $page = ChatMessage::where('conversation_id', $thread->id)
            ->orderByDesc('id')
            ->paginate(40);

        // Everything the counterpart sent is now seen.
        ChatMessage::where('conversation_id', $thread->id)
            ->where('sender_participation_id', '!=', $me)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'data' => collect($page->items())->reverse()->values()->map(fn (ChatMessage $m) => $this->message($m, $me)),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    /** Send a message (text and/or attachments) and fan it out over Reverb. */
    public function send(string $event, string $conversation, Request $request): JsonResponse
    {
        $me = (int) $request->attributes->get('participation_id');
        $thread = $this->resolveConversation($request, $conversation, $me);

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:4000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*.kind' => ['required_with:attachments', 'in:image,video,pdf,doc,excel,file'],
            'attachments.*.url' => ['required_with:attachments', 'url', 'max:2000'],
            'attachments.*.name' => ['nullable', 'string', 'max:255'],
        ]);

        $attachments = array_map(fn ($a) => [
            'kind' => $a['kind'],
            'url' => $a['url'],
            'name' => $a['name'] ?? null,
        ], $data['attachments'] ?? []);

        abort_if(trim((string) ($data['body'] ?? '')) === '' && ! $attachments, 422, 'Write something or attach a file.');

        $message = ChatMessage::create([
            'event_id' => $thread->event_id,
            'conversation_id' => $thread->id,
            'sender_participation_id' => $me,
            'body' => $data['body'] ?? '',
            'meta' => $attachments ? ['attachments' => $attachments] : null,
        ]);
        $thread->update(['last_message_at' => now()]);

        broadcast(new NewChatMessage($message, $thread->uuid, $thread->counterpartId($me)));

        return response()->json(['data' => $this->message($message, $me)], 201);
    }

    /** Mark the whole thread read (badge clearing while the thread is open). */
    public function read(string $event, string $conversation, Request $request): JsonResponse
    {
        $me = (int) $request->attributes->get('participation_id');
        $thread = $this->resolveConversation($request, $conversation, $me);

        $count = ChatMessage::where('conversation_id', $thread->id)
            ->where('sender_participation_id', '!=', $me)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['marked_read' => $count]);
    }

    // ── Internals ──────────────────────────────────────────────────────────

    /** A thread the current participant belongs to, or 404. */
    protected function resolveConversation(Request $request, string $uuid, int $me): ChatConversation
    {
        return ChatConversation::where('uuid', $uuid)
            ->where('event_id', $request->attributes->get('event_id'))
            ->where(fn ($q) => $q->where('a_participation_id', $me)->orWhere('b_participation_id', $me))
            ->firstOrFail();
    }

    /**
     * Roles the current participant may start a chat with, from the
     * event_settings.chat matrix. A missing matrix (or missing row) means the
     * organizer hasn't restricted anything — everyone may chat.
     */
    protected function allowedTargetRoles(Request $request): array
    {
        $me = Participation::find($request->attributes->get('participation_id'));
        $myRole = in_array($me?->role, self::CHAT_ROLES, true) ? $me->role : 'attendee';

        $matrix = Event::find($request->attributes->get('event_id'))
            ?->settings?->chat ?? [];
        $row = $matrix[$myRole] ?? null;

        if (! is_array($row)) {
            return self::CHAT_ROLES;
        }

        return array_values(array_filter(
            self::CHAT_ROLES,
            fn ($role) => (bool) ($row[$role] ?? true),
        ));
    }

    /** Directory projection of a participation (mirrors DelegateController). */
    protected function person(Participation $p): array
    {
        $c = $p->contact;
        $meta = $p->meta ?? [];
        $profile = $p->profile_data ?? [];

        return [
            'id' => $p->uuid,
            'name' => $c ? (trim(($c->first_name ?? '').' '.($c->last_name ?? '')) ?: 'Attendee') : 'Attendee',
            'role' => $p->role ?? 'attendee',
            'company' => $c?->company ?? ($profile['company'] ?? ''),
            'job_title' => $c?->job_title ?? ($profile['designation'] ?? ''),
            'avatar_url' => $meta['avatar_url'] ?? ($profile['avatar_url'] ?? ($profile['image_url'] ?? null)),
        ];
    }

    /** Wire shape of one message. */
    protected function message(ChatMessage $m, int $me): array
    {
        return [
            'id' => $m->uuid,
            'body' => $m->body,
            'attachments' => ($m->meta ?? [])['attachments'] ?? [],
            'mine' => $m->sender_participation_id === $me,
            'read_at' => $m->read_at?->toIso8601String(),
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }

    /** Inbox preview line for a message that is only an attachment. */
    public static function previewLabel(ChatMessage $m): string
    {
        if (trim((string) $m->body) !== '') {
            return $m->body;
        }
        $first = (($m->meta ?? [])['attachments'] ?? [])[0] ?? null;

        return match ($first['kind'] ?? null) {
            'image' => '📷 Photo',
            'video' => '🎬 Video',
            'pdf', 'doc', 'excel', 'file' => '📎 '.($first['name'] ?: 'File'),
            default => '',
        };
    }
}
