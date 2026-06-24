<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\NormalizesTimestamps;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Event;
use App\Models\Participation;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Targeted broadcast announcements (architecture §6.6). Organizer-side.
 */
class AnnouncementController extends Controller
{
    use NormalizesTimestamps;

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Announcement::query()->latest('id');

        if ($request->filled('event')) {
            $event = Event::where('uuid', $request->string('event'))->firstOrFail();
            $query->where('event_id', $event->id);
        }

        return JsonResource::collection($query->get(['id', 'title', 'status', 'scheduled_at', 'sent_at']));
    }

    public function store(Request $request, NotificationService $notifications): JsonResponse
    {
        $data = $request->validate([
            'event' => ['required', 'string'],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['nullable', 'string'],
            'audience' => ['nullable', 'array'],
            'channels' => ['nullable', 'array'],
            'scheduled_at' => ['nullable', 'date'],
        ]);

        $event = Event::where('uuid', $data['event'])->firstOrFail();
        $data = $this->utcDates($data, ['scheduled_at']);
        $scheduled = $data['scheduled_at'] ?? null;

        $announcement = Announcement::create([
            'event_id' => $event->id,
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'audience' => $data['audience'] ?? ['all' => true],
            'channels' => $data['channels'] ?? ['in_app' => true],
            'scheduled_at' => $scheduled,
            'sent_at' => $scheduled ? null : now(),
            'status' => $scheduled ? 'scheduled' : 'sent',
            'created_by' => $request->user()->id,
        ]);

        // Fan-out in-app notifications to every participant (small events: inline;
        // a queued job would handle large audiences in production).
        if ($announcement->status === 'sent') {
            Participation::where('event_id', $event->id)->pluck('id')
                ->each(fn ($pid) => $notifications->notify(
                    'participation', $pid, $event->organization_id, $event->id,
                    'announcement.posted', ['title' => $announcement->title, 'body' => $announcement->body],
                ));
        }

        return response()->json([
            'data' => $announcement->only('id', 'title', 'status'),
        ], 201);
    }
}
