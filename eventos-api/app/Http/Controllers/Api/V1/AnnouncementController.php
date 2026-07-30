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
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Targeted broadcast announcements (architecture §6.6). Organizer-side.
 * Powers Bulk Notification: draft, schedule, and send to event participants.
 */
class AnnouncementController extends Controller
{
    use NormalizesTimestamps;

    private const DISPLAY_AREAS = [
        'all_pages',
        'single_exhibitor',
        'all_exhibitors',
        'single_sponsor',
        'all_sponsors',
        'single_session',
        'all_sessions',
        'single_contest',
        'all_contests',
        'reception',
        'event_feed',
        'speakers',
        'delegates',
        'meetings_feed',
    ];

    public function index(Request $request): JsonResponse
    {
        $query = Announcement::query()->latest('id');

        if ($request->filled('event')) {
            $event = Event::where('uuid', $request->string('event'))->firstOrFail();
            $query->where('event_id', $event->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $rows = $query->get()->map(fn (Announcement $a) => $this->serialize($a))->values();

        return response()->json(['data' => $rows]);
    }

    public function show(int $announcement): JsonResponse
    {
        $row = Announcement::findOrFail($announcement);

        return response()->json(['data' => $this->serialize($row)]);
    }

    public function store(Request $request, NotificationService $notifications): JsonResponse
    {
        $data = $this->validated($request);
        $event = Event::where('uuid', $data['event'])->firstOrFail();
        $data = $this->utcDates($data, ['scheduled_at']);

        [$status, $scheduledAt, $sentAt] = $this->resolveStatus($data);

        $announcement = Announcement::create([
            'event_id' => $event->id,
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'display_area' => $data['display_area'] ?? null,
            'audience' => $this->normalizeAudience($data['audience'] ?? null),
            'channels' => $this->normalizeChannels($data['channels'] ?? null),
            'scheduled_at' => $scheduledAt,
            'sent_at' => $sentAt,
            'status' => $status,
            'created_by' => $request->user()->id,
        ]);

        if ($status === 'sent') {
            $this->fanOut($announcement, $event, $notifications);
        }

        return response()->json(['data' => $this->serialize($announcement)], 201);
    }

    public function update(Request $request, int $announcement, NotificationService $notifications): JsonResponse
    {
        $row = Announcement::findOrFail($announcement);

        if ($row->status === 'sent') {
            return response()->json(['message' => 'Sent notifications cannot be edited.'], 422);
        }

        $data = $this->validated($request, updating: true);
        $data = $this->utcDates($data, ['scheduled_at']);

        [$status, $scheduledAt, $sentAt] = $this->resolveStatus($data, $row);

        $row->fill([
            'title' => $data['title'] ?? $row->title,
            'body' => array_key_exists('body', $data) ? $data['body'] : $row->body,
            'display_area' => array_key_exists('display_area', $data) ? $data['display_area'] : $row->display_area,
            'audience' => array_key_exists('audience', $data)
                ? $this->normalizeAudience($data['audience'])
                : $row->audience,
            'channels' => array_key_exists('channels', $data)
                ? $this->normalizeChannels($data['channels'])
                : $row->channels,
            'scheduled_at' => $scheduledAt,
            'sent_at' => $sentAt,
            'status' => $status,
        ])->save();

        if ($status === 'sent') {
            $event = Event::findOrFail($row->event_id);
            $this->fanOut($row, $event, $notifications);
        }

        return response()->json(['data' => $this->serialize($row->fresh())]);
    }

    public function send(int $announcement, NotificationService $notifications): JsonResponse
    {
        $row = Announcement::findOrFail($announcement);

        if ($row->status === 'sent') {
            return response()->json(['message' => 'This notification was already sent.'], 422);
        }

        $event = Event::findOrFail($row->event_id);

        $row->update([
            'status' => 'sent',
            'scheduled_at' => null,
            'sent_at' => now(),
        ]);

        $this->fanOut($row, $event, $notifications);

        return response()->json(['data' => $this->serialize($row->fresh())]);
    }

    public function destroy(int $announcement): JsonResponse
    {
        $row = Announcement::findOrFail($announcement);
        $row->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array{0: string, 1: mixed, 2: mixed}
     */
    private function resolveStatus(array $data, ?Announcement $existing = null): array
    {
        $intent = $data['status'] ?? null;
        $scheduled = $data['scheduled_at'] ?? null;

        if ($intent === 'draft') {
            return ['draft', null, null];
        }

        if ($intent === 'scheduled' || ($scheduled && $intent !== 'sent')) {
            return ['scheduled', $scheduled ?? $existing?->scheduled_at, null];
        }

        if ($intent === 'sent') {
            return ['sent', null, now()];
        }

        // Legacy create: no status + optional schedule.
        if (! $existing) {
            return $scheduled
                ? ['scheduled', $scheduled, null]
                : ['sent', null, now()];
        }

        return [$existing->status, $existing->scheduled_at, $existing->sent_at];
    }

    private function validated(Request $request, bool $updating = false): array
    {
        $title = $updating ? ['sometimes', 'required', 'string', 'max:200'] : ['required', 'string', 'max:200'];
        $event = $updating ? ['sometimes', 'string'] : ['required', 'string'];

        return $request->validate([
            'event' => $event,
            'title' => $title,
            'body' => ['nullable', 'string'],
            'display_area' => ['nullable', 'string', Rule::in(self::DISPLAY_AREAS)],
            'audience' => ['nullable', 'array'],
            'audience.all' => ['sometimes', 'boolean'],
            'audience.specific' => ['sometimes', 'boolean'],
            'audience.roles' => ['sometimes', 'array'],
            'audience.roles.*' => ['string', Rule::in(['attendee', 'speaker', 'exhibitor', 'sponsor'])],
            'audience.user_ids' => ['sometimes', 'array'],
            'audience.user_ids.*' => ['string'],
            'audience.target_id' => ['nullable', 'string'],
            'audience.target_label' => ['nullable', 'string', 'max:200'],
            'channels' => ['nullable', 'array'],
            'channels.web' => ['sometimes', 'boolean'],
            'channels.mobile' => ['sometimes', 'boolean'],
            'channels.in_app' => ['sometimes', 'boolean'],
            'channels.push' => ['sometimes', 'boolean'],
            'scheduled_at' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(['draft', 'scheduled', 'sent'])],
        ]);
    }

    private function normalizeAudience(?array $audience): array
    {
        if (! $audience) {
            return ['all' => true, 'roles' => []];
        }

        $specific = ! empty($audience['specific']) || ! empty($audience['user_ids']);
        $roles = array_values(array_unique(array_filter($audience['roles'] ?? [])));
        $all = array_key_exists('all', $audience)
            ? (bool) $audience['all']
            : (! $specific && empty($roles));

        return [
            'all' => $all && ! $specific && empty($roles),
            'specific' => $specific,
            'roles' => $specific || $all ? [] : $roles,
            'user_ids' => array_values(array_unique(array_filter($audience['user_ids'] ?? []))),
            'target_id' => $audience['target_id'] ?? null,
            'target_label' => $audience['target_label'] ?? null,
        ];
    }

    private function normalizeChannels(?array $channels): array
    {
        if (! $channels) {
            return ['web' => true, 'mobile' => true, 'in_app' => true];
        }

        $web = (bool) ($channels['web'] ?? $channels['in_app'] ?? false);
        $mobile = (bool) ($channels['mobile'] ?? $channels['push'] ?? false);

        if (! $web && ! $mobile) {
            $web = true;
        }

        return [
            'web' => $web,
            'mobile' => $mobile,
            'in_app' => $web,
            'push' => $mobile,
        ];
    }

    private function fanOut(Announcement $announcement, Event $event, NotificationService $notifications): void
    {
        $channels = [];
        $ch = $announcement->channels ?? [];
        if (! empty($ch['web']) || ! empty($ch['in_app'])) {
            $channels[] = 'in_app';
        }
        if (! empty($ch['mobile']) || ! empty($ch['push'])) {
            $channels[] = 'push';
        }
        if (! $channels) {
            $channels = ['in_app'];
        }

        $payload = [
            'title' => $announcement->title,
            'body' => $this->plainText($announcement->body),
            'display_area' => $announcement->display_area,
        ];

        $this->audienceQuery($announcement, $event)->pluck('id')
            ->each(fn ($pid) => $notifications->notify(
                'participation', $pid, $event->organization_id, $event->id,
                'announcement.posted', $payload, $channels,
            ));
    }

    /** Rich-text body → readable single-line text for the in-app bell. */
    private function plainText(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return null;
        }

        $text = preg_replace('/<(br|\/p|\/div|\/li)\s*\/?>/i', ' ', $html);
        $text = strip_tags((string) $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        return $text === '' ? null : $text;
    }

    private function audienceQuery(Announcement $announcement, Event $event): Collection
    {
        $audience = $announcement->audience ?? ['all' => true];
        $query = Participation::where('event_id', $event->id);

        if (! empty($audience['specific']) && ! empty($audience['user_ids'])) {
            $query->whereIn('uuid', $audience['user_ids']);
        } elseif (! empty($audience['roles']) && empty($audience['all'])) {
            $roles = array_values($audience['roles']);
            $query->where(function ($q) use ($roles) {
                foreach ($roles as $role) {
                    if ($role === 'exhibitor') {
                        $q->orWhereIn('role', ['exhibitor', 'partner_member']);
                    } else {
                        $q->orWhere('role', $role);
                    }
                }
            });
        }

        return $query->get(['id']);
    }

    private function serialize(Announcement $a): array
    {
        return [
            'id' => $a->id,
            'title' => $a->title,
            'body' => $a->body,
            'display_area' => $a->display_area,
            'audience' => $a->audience ?? ['all' => true, 'roles' => []],
            'channels' => $a->channels ?? ['web' => true, 'mobile' => true],
            'status' => $a->status,
            'scheduled_at' => optional($a->scheduled_at)?->toIso8601String(),
            'sent_at' => optional($a->sent_at)?->toIso8601String(),
            'created_at' => optional($a->created_at)?->toIso8601String(),
            'reach' => 0,
            'clicked' => 0,
        ];
    }
}
