<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BreakoutRoomResource;
use App\Models\BreakoutRoom;
use App\Models\Event;
use App\Services\BreakoutRoom\Providers\LiveKitProvider;
use App\Services\BreakoutRoom\Providers\MeetingProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Breakout Rooms (Event Engagement). Event-scoped on index/store; id-based on
 * show/update/destroy/duplicate/status (resolved here so the tenant GUC is set
 * and RLS doesn't hide the row at bind time). JSON `meta` read via
 * $request->input() since validate() strips nested keys. Mirrors the
 * EventAdController conventions.
 */
class BreakoutRoomController extends Controller
{
    private const TYPES = [
        'workshop', 'networking', 'round_table', 'sponsor_demo', 'team',
        'private', 'vip', 'interview', 'panel', 'ama', 'custom',
    ];
    private const ACCESS_TYPES = ['anyone', 'coded', 'hidden'];
    private const PURPOSES = ['single', 'multiple'];
    private const PROVIDERS = ['webrtc', 'zoom', 'teams', 'meet', 'jitsi', 'bbb', 'external'];
    private const STATUSES = ['draft', 'published', 'archived'];

    public function index(string $uuid, Request $request): AnonymousResourceCollection
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $query = BreakoutRoom::where('event_id', $event->id)->orderByDesc('id');
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return BreakoutRoomResource::collection($query->get());
    }

    public function store(Request $request, string $uuid): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $data = $request->validate($this->rules(required: true));

        $room = BreakoutRoom::create($this->payload($request, $data) + [
            'event_id'   => $event->id,
            'status'     => $data['status'] ?? 'draft',
            'created_by' => $request->user()?->id,
        ]);
        $this->syncPublishedAt($room);

        return response()->json(['data' => new BreakoutRoomResource($room)], 201);
    }

    public function show(int $room): JsonResponse
    {
        return response()->json(['data' => new BreakoutRoomResource(BreakoutRoom::findOrFail($room))]);
    }

    public function update(Request $request, int $room): JsonResponse
    {
        $model = BreakoutRoom::findOrFail($room);

        $data = $request->validate($this->rules(required: false));

        foreach ($this->payload($request, $data, partial: true) as $key => $value) {
            $model->{$key} = $value;
        }
        if ($request->has('status')) {
            $model->status = $data['status'];
        }
        $model->updated_by = $request->user()?->id;
        $this->syncPublishedAt($model);
        $model->save();

        return response()->json(['data' => new BreakoutRoomResource($model)]);
    }

    public function destroy(int $room): JsonResponse
    {
        BreakoutRoom::findOrFail($room)->delete();

        return response()->json(['status' => 'success']);
    }

    /**
     * Mint a media join token for the current user. Enforces the room's access
     * rules (coded → matching code; archived → closed) and derives the WebRTC
     * grant from the user's role. Returns { provider, url, room, token }.
     *
     * Role resolution is coarse for now (manager → host, else attendee); it will
     * read breakout_room_roles once participant infra lands (architecture §4).
     */
    public function token(Request $request): JsonResponse
    {
        // Read the room id from the route (not a typed arg): this endpoint is
        // reachable both as /breakout-rooms/{room}/token and, for attendees, as
        // /events/{event}/breakout-rooms/{room}/token — and Laravel binds scalar
        // route params to controller args positionally, so a typed $room would
        // capture {event} on the two-param route.
        $model = BreakoutRoom::findOrFail((int) $request->route('room'));
        $user = $request->user();
        $isManager = (bool) $user?->hasPermission('events.manage');

        if ($model->status === 'archived') {
            throw ValidationException::withMessages(['room' => 'This room is closed.']);
        }
        // Attendees may only join published rooms; managers can preview drafts.
        if (! $isManager && $model->status !== 'published') {
            throw ValidationException::withMessages(['room' => 'This room is not open yet.']);
        }
        if ($model->access_type === 'coded') {
            $code = (string) $request->input('access_code');
            if (! hash_equals((string) $model->access_code, $code)) {
                throw ValidationException::withMessages(['access_code' => 'Incorrect access code.']);
            }
        }

        $role = $isManager ? 'host' : 'attendee';

        $config = $this->provider($model)->joinConfig($model, [
            'identity' => 'user_'.($user?->id ?? 'guest'),
            'name' => $user?->name ?? 'Guest',
            'role' => $role,
            // Participatory rooms (round table, networking, workshop, …) let
            // attendees publish their own mic/camera so they can take part;
            // broadcast rooms (panel/AMA/…) keep them as watch-only viewers.
            'canPublish' => $isManager || $model->attendeesCanPublish(),
        ]);

        return response()->json(['data' => $config]);
    }

    /** Resolve the media backend for a room from its `provider` column. */
    private function provider(BreakoutRoom $room): MeetingProvider
    {
        return match ($room->provider) {
            'webrtc' => app(LiveKitProvider::class),
            default => throw ValidationException::withMessages([
                'provider' => "The '{$room->provider}' provider is not yet available.",
            ]),
        };
    }

    /** Deep-copy a room (as a fresh draft) for quick reuse. */
    public function duplicate(int $room, Request $request): JsonResponse
    {
        $source = BreakoutRoom::findOrFail($room);

        $copy = $source->replicate(['uuid', 'published_at', 'created_at', 'updated_at', 'deleted_at']);
        $copy->name = mb_substr($source->name.' (Copy)', 0, 200);
        $copy->status = 'draft';
        $copy->published_at = null;
        $copy->created_by = $request->user()?->id;
        $copy->updated_by = null;
        $copy->save();

        return response()->json(['data' => new BreakoutRoomResource($copy)], 201);
    }

    /** Publish / unpublish (back to draft) / archive. */
    public function setStatus(int $room, Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(self::STATUSES)]]);

        $model = BreakoutRoom::findOrFail($room);
        $model->status = $data['status'];
        $model->updated_by = $request->user()?->id;
        $this->syncPublishedAt($model);
        $model->save();

        return response()->json(['data' => new BreakoutRoomResource($model)]);
    }

    /** Map validated input to column values; `partial` only touches provided keys. */
    private function payload(Request $request, array $data, bool $partial = false): array
    {
        $columns = [
            'name', 'description', 'purpose', 'type', 'access_type', 'access_code',
            'capacity', 'poster_url', 'provider', 'meeting_url', 'recording_enabled',
            'starts_at', 'ends_at', 'meta',
        ];

        $out = [];
        foreach ($columns as $col) {
            if ($partial && ! $request->has($col)) {
                continue;
            }
            $out[$col] = $data[$col] ?? $request->input($col);
        }

        // An open ("anyone") or unlisted ("hidden") room carries no access code.
        $accessType = $out['access_type'] ?? $request->input('access_type');
        if ($accessType && $accessType !== 'coded') {
            if (! $partial || $request->has('access_type')) {
                $out['access_code'] = null;
            }
        }

        return $out;
    }

    /** Keep published_at in step with status transitions. */
    private function syncPublishedAt(BreakoutRoom $room): void
    {
        if ($room->status === 'published' && ! $room->published_at) {
            $room->published_at = Carbon::now();
        }
        if ($room->status === 'draft') {
            $room->published_at = null;
        }
    }

    private function rules(bool $required): array
    {
        $req = $required ? 'required' : 'sometimes';

        return [
            'name'              => [$req, 'string', 'max:200'],
            'description'       => ['nullable', 'string', 'max:5000'],
            'purpose'           => ['nullable', Rule::in(self::PURPOSES)],
            'type'              => ['nullable', Rule::in(self::TYPES)],
            'access_type'       => ['nullable', Rule::in(self::ACCESS_TYPES)],
            'access_code'       => ['nullable', 'string', 'max:60', 'required_if:access_type,coded'],
            'capacity'          => ['nullable', 'integer', 'min:1', 'max:100000'],
            'poster_url'        => ['nullable', 'string', 'max:2000'],
            'provider'          => ['nullable', Rule::in(self::PROVIDERS)],
            'meeting_url'       => ['nullable', 'string', 'max:2000'],
            'recording_enabled' => ['nullable', 'boolean'],
            'status'            => ['nullable', Rule::in(self::STATUSES)],
            'starts_at'         => ['nullable', 'date'],
            'ends_at'           => ['nullable', 'date', 'after_or_equal:starts_at'],
            'meta'              => ['nullable', 'array'],
        ];
    }
}
