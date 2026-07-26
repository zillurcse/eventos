<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ParticipantResource;
use App\Models\Event;
use App\Models\File;
use App\Models\Participation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Organizer-side directory of an event's people (the "Users" section). A user is
 * a Participation (+ its Contact) on the event. Block state lives in
 * participation.meta.blocked so it's independent of the registration status enum.
 */
class ParticipantController extends Controller
{
    public function index(string $uuid, Request $request): AnonymousResourceCollection
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $query = Participation::with('contact')
            ->where('event_id', $event->id);

        if ($request->filled('role')) {
            $query->where('role', $request->string('role'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->has('blocked')) {
            $request->boolean('blocked')
                ? $query->where('meta->blocked', true)
                : $query->where(fn ($q) => $q->where('meta->blocked', false)->orWhereNull('meta->blocked'));
        }
        // WebApp users = participants whose contact has a login (user_id).
        if ($request->boolean('has_login')) {
            $query->whereHas('contact', fn ($c) => $c->whereNotNull('user_id'));
        }
        if ($request->filled('q')) {
            $term = '%'.strtolower((string) $request->string('q')).'%';
            $query->whereHas('contact', function ($c) use ($term) {
                $c->whereRaw('lower(email) like ?', [$term])
                    ->orWhereRaw("lower(coalesce(first_name,'')||' '||coalesce(last_name,'')) like ?", [$term]);
            });
        }

        $rows = $query->latest('id')->get();
        ParticipantResource::$photoUrls = $this->avatarFileUrls($rows);

        return ParticipantResource::collection($rows);
    }

    /**
     * Resolve contact photo soft references in one query for the whole list.
     *
     * @return array<int, string>
     */
    private function avatarFileUrls(Collection $rows): array
    {
        $ids = $rows
            ->map(fn (Participation $p) => $p->contact?->photo_file_id)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return File::on('pgsql_admin')
            ->whereIn('id', $ids)
            ->get(['id', 'disk', 'path'])
            ->mapWithKeys(fn (File $file) => [
                $file->id => Storage::disk($file->disk)->url($file->path),
            ])
            ->all();
    }

    /** Block or unblock a participant (stored in meta.blocked). */
    public function setBlocked(string $uuid, string $participationUuid, Request $request): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();
        $participation = Participation::where('uuid', $participationUuid)
            ->where('event_id', $event->id)
            ->firstOrFail();

        $data = $request->validate(['blocked' => ['required', 'boolean']]);

        $participation->update([
            'meta' => array_merge($participation->meta ?? [], ['blocked' => $data['blocked']]),
        ]);

        $fresh = $participation->fresh('contact');
        ParticipantResource::$photoUrls = $this->avatarFileUrls(collect([$fresh]));

        return response()->json(['data' => new ParticipantResource($fresh)]);
    }

    public function destroy(string $uuid, string $participationUuid): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();
        $participation = Participation::where('uuid', $participationUuid)
            ->where('event_id', $event->id)
            ->firstOrFail();

        $participation->delete();

        return response()->json(['status' => 'success']);
    }
}
