<?php

namespace App\Services\Notifications;

use App\Models\Contact;
use App\Models\Event;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\NotificationTemplate;
use App\Models\Participation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Renders + records + delivers notifications across channels (architecture
 * §6.7). in_app is stored for the recipient to poll; email goes out via the
 * mail transport (Mailpit in dev); push/sms are recorded as queued (driver TBD).
 *
 * Delivery is the intersection of (1) the caller’s channel list — usually the
 * event Communication → Notification matrix via {@see channelsForEventAction} —
 * and (2) the recipient’s Profile › Notifications preferences for the
 * template key’s category.
 */
class NotificationService
{
    /**
     * Channels enabled for an automatic trigger in Communication → Notification.
     * Admin matrix keys are web/email/sms; delivery uses in_app for web.
     * A missing matrix (or missing action row) matches the admin UI defaults:
     * all channels on.
     *
     * @return list<string>
     */
    public function channelsForEventAction(int $eventId, string $action): array
    {
        $matrix = Event::find($eventId)?->settings?->notifications ?? [];
        $row = is_array($matrix[$action] ?? null) ? $matrix[$action] : null;

        $web = $row === null ? true : (bool) ($row['web'] ?? true);
        $email = $row === null ? true : (bool) ($row['email'] ?? true);
        $sms = $row === null ? true : (bool) ($row['sms'] ?? true);

        $channels = [];
        if ($web) {
            $channels[] = 'in_app';
        }
        if ($email) {
            $channels[] = 'email';
        }
        if ($sms) {
            $channels[] = 'sms';
        }

        return $channels;
    }

    /**
     * @param  array<int,string>  $channels
     */
    public function notify(
        string $notifiableType,
        int $notifiableId,
        ?int $organizationId,
        ?int $eventId,
        string $key,
        array $data = [],
        array $channels = ['in_app'],
    ): void {
        $userId = $this->resolveUserId($notifiableType, $notifiableId);
        if ($userId !== null) {
            $channels = $this->filterChannelsByPreference($userId, $key, $channels);
        }

        if ($channels === []) {
            return;
        }

        if (in_array('email', $channels, true) && empty($data['email'])) {
            $email = $this->resolveEmail($notifiableType, $notifiableId);
            if ($email) {
                $data['email'] = $email;
            } else {
                $channels = array_values(array_filter($channels, fn (string $c) => $c !== 'email'));
            }
        }

        if ($channels === []) {
            return;
        }

        foreach ($channels as $channel) {
            $template = NotificationTemplate::where('key', $key)->where('channel', $channel)->first();

            $title = $template?->subject ? $this->render($template->subject, $data) : ($data['title'] ?? $key);
            $body = $template?->body ? $this->render($template->body, $data) : ($data['body'] ?? null);

            $notification = Notification::create([
                'organization_id' => $organizationId,
                'event_id' => $eventId,
                'notifiable_type' => $notifiableType,
                'notifiable_id' => $notifiableId,
                'channel' => $channel,
                'template_key' => $key,
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'status' => $channel === 'in_app' ? 'delivered' : 'queued',
                'created_at' => now(),
            ]);

            if ($channel === 'email' && ! empty($data['email'])) {
                Mail::html('<p>'.nl2br(e((string) $body)).'</p>', fn ($m) => $m->to($data['email'])->subject($title));
                $notification->update(['status' => 'sent', 'sent_at' => now()]);
            }
        }
    }

    /**
     * Drop channels the recipient has opted out of for this template key.
     * SMS is left to the event matrix only (no user SMS toggle in settings).
     * Push follows the same Mobile & Desktop (`in_app`) toggle the UI exposes.
     *
     * @param  list<string>  $channels
     * @return list<string>
     */
    public function filterChannelsByPreference(int $userId, string $key, array $channels): array
    {
        $map = $this->preferenceCategoriesForKey($key);
        if ($map === null) {
            return array_values($channels);
        }

        $kept = [];
        foreach ($channels as $channel) {
            $category = match ($channel) {
                'email' => $map['email'],
                'in_app', 'push' => $map['in_app'],
                default => null,
            };

            if ($category === null) {
                $kept[] = $channel;
                continue;
            }

            $pref = $this->preferenceFor($userId, $category);
            $allowed = match ($channel) {
                'email' => $pref['email'],
                'in_app', 'push' => $pref['in_app'],
                default => true,
            };

            if ($allowed) {
                $kept[] = $channel;
            }
        }

        return $kept;
    }

    /**
     * Preference category used for email vs in-app/push for a template key.
     * Meetings split across `meetings` (email) and `meeting_status` (bell/push)
     * to match Profile › Notifications rows. Unknown keys are not gated.
     *
     * @return array{email: string, in_app: string}|null
     */
    public function preferenceCategoriesForKey(string $key): ?array
    {
        return match (true) {
            str_starts_with($key, 'meeting.') || str_starts_with($key, 'exhibitor.meeting_') => [
                'email' => 'meetings',
                'in_app' => 'meeting_status',
            ],
            str_starts_with($key, 'exhibitor.message') || $key === 'exhibitor.connection_request' => [
                'email' => 'messages',
                'in_app' => 'messages',
            ],
            $key === 'engagement.profile_viewed' || $key === 'engagement.profile_saved' => [
                'email' => 'profile_views',
                'in_app' => 'profile_views',
            ],
            str_starts_with($key, 'announcement.') => [
                'email' => 'organiser',
                'in_app' => 'organiser',
            ],
            str_starts_with($key, 'connection.') => [
                'email' => 'organiser',
                'in_app' => 'organiser',
            ],
            str_contains($key, 'mention') => [
                'email' => 'mentions',
                'in_app' => 'mentions',
            ],
            str_starts_with($key, 'session.') && str_contains($key, 'live') => [
                'email' => 'session_live',
                'in_app' => 'session_live',
            ],
            default => null,
        };
    }

    /**
     * @return array{email: bool, in_app: bool}
     */
    public function preferenceFor(int $userId, string $category): array
    {
        $defaults = NotificationPreference::CATEGORIES[$category]
            ?? ['email' => true, 'in_app' => true];

        $row = NotificationPreference::on('pgsql_admin')
            ->where('user_id', $userId)
            ->where('category', $category)
            ->whereNull('organization_id')
            ->whereNull('event_id')
            ->first();

        return [
            'email' => $row ? (bool) $row->email : (bool) $defaults['email'],
            'in_app' => $row ? (bool) $row->in_app : (bool) $defaults['in_app'],
        ];
    }

    protected function resolveUserId(string $notifiableType, int $notifiableId): ?int
    {
        return match ($notifiableType) {
            'user' => $notifiableId,
            'contact' => Contact::on('pgsql_admin')
                ->withoutGlobalScopes()
                ->where('id', $notifiableId)
                ->value('user_id'),
            'participation' => Participation::on('pgsql_admin')
                ->withoutGlobalScopes()
                ->where('participations.id', $notifiableId)
                ->join('contacts', 'contacts.id', '=', 'participations.contact_id')
                ->value('contacts.user_id'),
            default => null,
        };
    }

    protected function resolveEmail(string $notifiableType, int $notifiableId): ?string
    {
        $email = match ($notifiableType) {
            'contact' => Contact::on('pgsql_admin')
                ->withoutGlobalScopes()
                ->where('id', $notifiableId)
                ->value('email'),
            'participation' => Participation::on('pgsql_admin')
                ->withoutGlobalScopes()
                ->where('participations.id', $notifiableId)
                ->join('contacts', 'contacts.id', '=', 'participations.contact_id')
                ->value('contacts.email'),
            'user' => User::on('pgsql_admin')->where('id', $notifiableId)->value('email'),
            default => null,
        };

        return is_string($email) && $email !== '' ? $email : null;
    }

    protected function render(string $template, array $data): string
    {
        return preg_replace_callback(
            '/\{\{\s*(\w+)\s*\}\}/',
            fn ($m) => (string) ($data[$m[1]] ?? ''),
            $template,
        );
    }
}
