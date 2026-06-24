<?php

namespace App\Services\Notifications;

use App\Models\Notification;
use App\Models\NotificationTemplate;
use Illuminate\Support\Facades\Mail;

/**
 * Renders + records + delivers notifications across channels (architecture
 * §6.7). in_app is stored for the recipient to poll; email goes out via the
 * mail transport (Mailpit in dev); push/sms are recorded as queued (driver TBD).
 */
class NotificationService
{
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

    protected function render(string $template, array $data): string
    {
        return preg_replace_callback(
            '/\{\{\s*(\w+)\s*\}\}/',
            fn ($m) => (string) ($data[$m[1]] ?? ''),
            $template,
        );
    }
}
