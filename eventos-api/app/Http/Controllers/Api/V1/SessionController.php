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
            'event' => ['required', 'string'],
            'title' => ['required', 'string', 'max:250'],
            'description' => ['nullable', 'string'],
            'track_id' => ['nullable', 'integer', 'exists:tracks,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'starts_at' => ['nullable', 'date'],   // ISO-8601 w/ offset → stored UTC
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'timezone' => ['nullable', 'string', 'max:64'], // IANA override
            'capacity' => ['nullable', 'integer', 'min:0'],
            'stream_url' => ['nullable', 'url', 'max:500'],
        ]);

        $event = Event::where('uuid', $data['event'])->firstOrFail();
        $data = $this->utcDates($data, ['starts_at', 'ends_at']);

        $session = Session::create([
            'event_id' => $event->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'track_id' => $data['track_id'] ?? null,
            'room_id' => $data['room_id'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'timezone' => $data['timezone'] ?? null,
            'capacity' => $data['capacity'] ?? null,
            'stream_url' => $data['stream_url'] ?? null,
            'status' => 'scheduled',
        ]);

        return response()->json(['data' => new SessionResource($session->load('event'))], 201);
    }

    public function update(string $uuid, Request $request): JsonResponse
    {
        $session = Session::where('uuid', $uuid)->firstOrFail();

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:250'],
            'description' => ['nullable', 'string'],
            'track_id' => ['nullable', 'integer', 'exists:tracks,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'stream_url' => ['nullable', 'url', 'max:500'],
            'status' => ['sometimes', 'in:scheduled,live,ended,canceled'],
        ]);

        $session->update($this->utcDates($data, ['starts_at', 'ends_at']));

        return response()->json(['data' => new SessionResource($session->fresh()->load('event'))]);
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
}
