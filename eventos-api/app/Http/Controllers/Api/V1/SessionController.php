<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\NormalizesTimestamps;
use App\Http\Controllers\Controller;
use App\Http\Resources\SessionResource;
use App\Models\Contact;
use App\Models\Event;
use App\Models\Participation;
use App\Models\Session;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SessionController extends Controller
{
    use NormalizesTimestamps;

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Session::with(['track', 'room', 'speakers.contact', 'event'])->orderBy('starts_at');

        if ($request->filled('event')) {
            $event = Event::where('uuid', $request->string('event'))->firstOrFail();
            $query->where('event_id', $event->id);
        }

        return SessionResource::collection($query->get());
    }

    public function show(string $uuid): JsonResponse
    {
        $session = Session::with(['track', 'room', 'speakers.contact', 'event'])
            ->where('uuid', $uuid)->firstOrFail();

        return response()->json(['data' => new SessionResource($session)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event'              => ['required', 'string'],
            'title'              => ['required', 'string', 'max:250'],
            'description'        => ['nullable', 'string'],
            'track_id'           => ['nullable', 'integer', 'exists:tracks,id'],
            'room_id'            => ['nullable', 'integer', 'exists:rooms,id'],
            'starts_at'          => ['nullable', 'date'],
            'ends_at'            => ['nullable', 'date', 'after_or_equal:starts_at'],
            'timezone'           => ['nullable', 'string', 'max:64'],
            'capacity'           => ['nullable', 'integer', 'min:0'],
            'stream_url'         => ['nullable', 'url', 'max:500'],
            // Extra fields stored in meta
            'session_place'      => ['nullable', 'string', 'max:250'],
            'logo_url'           => ['nullable', 'string', 'max:2000'],
            'tags'               => ['nullable', 'array'],
            'tags.*'             => ['string', 'max:100'],
            'is_featured'        => ['nullable', 'boolean'],
            'is_allowed_to_rate' => ['nullable', 'boolean'],
            ...$this->extraMetaRules(),
        ]);

        $event = Event::where('uuid', $data['event'])->firstOrFail();
        $data  = $this->utcDates($data, ['starts_at', 'ends_at']);

        $session = Session::create([
            'event_id'   => $event->id,
            'title'      => $data['title'],
            'description' => $data['description'] ?? null,
            'track_id'   => $data['track_id'] ?? null,
            'room_id'    => $data['room_id'] ?? null,
            'starts_at'  => $data['starts_at'] ?? null,
            'ends_at'    => $data['ends_at'] ?? null,
            'timezone'   => $data['timezone'] ?? null,
            'capacity'   => $data['capacity'] ?? null,
            'stream_url' => $data['stream_url'] ?? null,
            'status'     => 'scheduled',
            'meta'       => [
                'session_place'      => $data['session_place'] ?? null,
                'logo_url'           => $data['logo_url'] ?? null,
                'icon_url'           => $data['icon_url'] ?? null,
                'tags'               => $data['tags'] ?? [],
                'sponsors'           => $data['sponsors'] ?? [],
                'documents'          => $data['documents'] ?? [],
                'is_featured'        => $data['is_featured'] ?? false,
                'is_allowed_to_rate' => $data['is_allowed_to_rate'] ?? false,
            ],
        ]);

        return response()->json(['data' => new SessionResource($session->load('event'))], 201);
    }

    public function update(string $uuid, Request $request): JsonResponse
    {
        $session = Session::where('uuid', $uuid)->firstOrFail();

        $data = $request->validate([
            'title'              => ['sometimes', 'string', 'max:250'],
            'description'        => ['nullable', 'string'],
            'track_id'           => ['nullable', 'integer', 'exists:tracks,id'],
            'room_id'            => ['nullable', 'integer', 'exists:rooms,id'],
            'starts_at'          => ['nullable', 'date'],
            'ends_at'            => ['nullable', 'date', 'after_or_equal:starts_at'],
            'timezone'           => ['nullable', 'string', 'max:64'],
            'capacity'           => ['nullable', 'integer', 'min:0'],
            'stream_url'         => ['nullable', 'url', 'max:500'],
            'status'             => ['sometimes', 'in:scheduled,live,ended,canceled'],
            // Extra meta fields
            'session_place'      => ['nullable', 'string', 'max:250'],
            'logo_url'           => ['nullable', 'string', 'max:2000'],
            'tags'               => ['nullable', 'array'],
            'tags.*'             => ['string', 'max:100'],
            'is_featured'        => ['nullable', 'boolean'],
            'is_allowed_to_rate' => ['nullable', 'boolean'],
            ...$this->extraMetaRules(),
        ]);

        $metaKeys = ['session_place', 'logo_url', 'icon_url', 'tags', 'sponsors', 'documents', 'is_featured', 'is_allowed_to_rate'];
        $metaUpdate = $request->only($metaKeys);

        $coreUpdate = $this->utcDates(
            collect($data)->except($metaKeys)->toArray(),
            ['starts_at', 'ends_at'],
        );

        if ($metaUpdate) {
            $coreUpdate['meta'] = array_merge($session->meta ?? [], $metaUpdate);
        }

        $session->update($coreUpdate);

        return response()->json(['data' => new SessionResource($session->fresh()->load('event'))]);
    }

    /**
     * Update streaming & engagement settings (meta) plus the broadcast state.
     *
     * `status` is a real column, not meta: it's the organizer's manual override
     * of the schedule — "go live now" / "we're done" — which the attendee watch
     * page honours ahead of starts_at/ends_at.
     */
    public function updateStream(string $uuid, Request $request): JsonResponse
    {
        $session = Session::where('uuid', $uuid)->firstOrFail();

        // Links are sometimes pasted percent-encoded ("?" as %3F, "=" as %3D),
        // which is still a valid URL but hides the query string — a YouTube
        // watch link then can't be recognised and silently degrades to an
        // "open in a new tab" button. Normalise before storing.
        $request->merge(array_filter([
            'stream_link' => $this->decodeLink($request->input('stream_link')),
            'on_demand_recording_link' => $this->decodeLink($request->input('on_demand_recording_link')),
        ], fn ($v) => $v !== null));

        // Jitsi and Agora take a bare room/channel name rather than a URL, so
        // only the hosts that genuinely need a URL are validated as one —
        // otherwise a typo'd link silently reaches attendees as a dead player.
        $urlHosts = ['zoom', 'youtube', 'meet', 'rtmp', 'self'];
        $needsUrl = in_array($request->input('who_will_host'), $urlHosts, true);

        $request->validate([
            'is_stream'                => ['nullable', 'boolean'],
            'who_will_host'            => ['nullable', 'in:self,zoom,rtmp,youtube,meet,vimeo,jitsi,agora'],
            'stream_link'              => ['nullable', 'string', 'max:500', ...($needsUrl ? ['url'] : [])],
            'on_demand_recording_link' => ['nullable', 'string', 'max:500', 'url'],
            'vimeo_live_id'            => ['nullable', 'string', 'max:250', 'regex:/^\d+$/'],
            'status'                   => ['nullable', 'in:scheduled,live,ended,canceled'],
            'can_live_chat'            => ['nullable', 'boolean'],
            'can_qa'                   => ['nullable', 'boolean'],
            'can_live_polls'           => ['nullable', 'boolean'],
            'can_attendee_list'        => ['nullable', 'boolean'],
            'can_session'              => ['nullable', 'boolean'],
            // Hold questions for host approval before attendees see them.
            'qa_moderation'            => ['nullable', 'boolean'],
            // Who may post a reply under a question (Session::canAnswerQa).
            'qa_answer_policy'         => ['nullable', 'in:'.implode(',', Session::QA_ANSWER_POLICIES)],
        ], [
            'vimeo_live_id.regex' => 'The Vimeo Live ID is the numeric event id, e.g. 123456789.',
        ]);

        $update = [
            'meta' => array_merge($session->meta ?? [], $request->only([
                'is_stream', 'who_will_host', 'stream_link', 'on_demand_recording_link',
                'vimeo_live_id', 'can_live_chat', 'can_qa', 'can_live_polls',
                'can_attendee_list', 'can_session', 'qa_moderation', 'qa_answer_policy',
            ])),
        ];

        if ($request->filled('status')) {
            $update['status'] = $request->input('status');
        }

        $session->update($update);

        return response()->json(['data' => new SessionResource($session->fresh()->load('event'))]);
    }

    public function destroy(string $uuid): JsonResponse
    {
        $session = Session::where('uuid', $uuid)->firstOrFail();
        $session->delete();

        return response()->json(null, 204);
    }

    /** Add a speaker: upsert contact → participation(role=speaker) → pivot. */
    public function addSpeaker(string $uuid, Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'first_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', 'in:speaker,moderator,panelist,keynote'],
        ]);

        $session = Session::where('uuid', $uuid)->firstOrFail();

        $contact = Contact::firstOrCreate(
            ['email' => $data['email']],
            ['first_name' => $data['first_name'] ?? null, 'last_name' => $data['last_name'] ?? null],
        );

        $participation = Participation::firstOrCreate(
            ['event_id' => $session->event_id, 'contact_id' => $contact->id, 'role' => 'speaker'],
            ['status' => 'confirmed'],
        );

        $session->speakers()->syncWithoutDetaching([
            $participation->id => ['role' => $data['role'] ?? 'speaker'],
        ]);

        return response()->json([
            'data' => new SessionResource($session->load(['event', 'speakers.contact'])),
        ]);
    }

    public function removeSpeaker(string $uuid, string $participationUuid): JsonResponse
    {
        $session = Session::where('uuid', $uuid)->firstOrFail();
        $participation = Participation::where('uuid', $participationUuid)->firstOrFail();

        $session->speakers()->detach($participation->id);

        return response()->json(['message' => 'Speaker removed.']);
    }

    /**
     * Validation for the meta-stored extras shared by store/update: the icon
     * image, session sponsors (denormalized {id,name,logo_url} refs from the
     * event's sponsor exhibitors) and uploaded documents ({name,url}, max 10).
     */
    private function extraMetaRules(): array
    {
        return [
            'icon_url'            => ['nullable', 'string', 'max:2000'],
            'sponsors'            => ['nullable', 'array'],
            'sponsors.*.id'       => ['required_with:sponsors', 'string'],
            'sponsors.*.name'     => ['nullable', 'string', 'max:250'],
            'sponsors.*.logo_url' => ['nullable', 'string', 'max:2000'],
            'documents'           => ['nullable', 'array', 'max:10'],
            'documents.*.name'    => ['required_with:documents', 'string', 'max:250'],
            'documents.*.url'     => ['required_with:documents', 'string', 'max:2000'],
        ];
    }

    /**
     * Undo percent-encoding on a pasted stream link. A copied URL sometimes
     * arrives with its query string escaped ("watch%3Fv%3Dabc" instead of
     * "watch?v=abc"): still a valid URL, so validation passes, but the video id
     * is now invisible to the player and the session degrades to an "open in a
     * new tab" link. Only decodes when the result is still an http(s) URL, so a
     * legitimately-encoded path segment isn't mangled.
     */
    private function decodeLink(mixed $link): ?string
    {
        if (! is_string($link) || $link === '') {
            return null;
        }

        $link = trim($link);
        if (! preg_match('/%(3F|3D|26)/i', $link)) {
            return $link;
        }

        $decoded = urldecode($link);

        return preg_match('#^https?://#i', $decoded) ? $decoded : $link;
    }
}
