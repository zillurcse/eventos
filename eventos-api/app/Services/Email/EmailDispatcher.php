<?php

namespace App\Services\Email;

use App\Models\EmailSend;
use App\Models\EmailTemplate;
use App\Models\EventSetting;
use Illuminate\Support\Facades\Mail;

/**
 * Compiles templates and sends rendered messages through the mail transport
 * (Mailpit in dev), recording each as an email_send (architecture §6.13).
 */
class EmailDispatcher
{
    public function __construct(
        protected EmailRenderer $renderer,
        protected MergeVariables $variables,
    ) {}

    /** Render the template's blocks → compiled_html (cached on the template). */
    public function compile(EmailTemplate $template): string
    {
        $html = $this->renderTemplate($template);

        $template->update(['compiled_html' => $html]);

        return $html;
    }

    /**
     * Compiled HTML with merge variables applied (no DB write). Recompiles each
     * call so the preview reflects in-flight edits, and falls back to realistic
     * sample values for any variable the caller didn't supply.
     */
    public function preview(EmailTemplate $template, array $merge = []): string
    {
        $vars = array_replace_recursive($this->variables->sampleData(), $merge);

        return $this->renderer->merge($this->renderTemplate($template), $vars);
    }

    /** The one place a template's stored design becomes HTML. */
    protected function renderTemplate(EmailTemplate $template): string
    {
        $design = $template->design ?? [];

        return $this->renderer->render(
            $design['blocks'] ?? [],
            $design['settings'] ?? [],
            $template->preheader,
        );
    }

    public function send(EmailTemplate $template, string $to, array $merge = [], string $trigger = 'test'): EmailSend
    {
        $subject = $this->renderer->merge($template->subject ?: $template->name, $merge);
        $html = $this->preview($template, $merge);

        // The template's own from/reply-to win; otherwise fall back to the event's
        // Sender Details. CC/BCC come from the event sender config.
        $sender    = $this->eventSender($template->event_id);
        $fromEmail = $template->from_email ?: ($sender['from'] ?? null);
        $fromName  = $template->from_name  ?: ($sender['sender_name'] ?? null);
        $replyTo   = $template->reply_to   ?: ($sender['reply_to'] ?? null);
        $cc        = $this->splitEmails($sender['cc'] ?? null);
        $bcc       = $this->splitEmails($sender['bcc'] ?? null);

        Mail::mailer($this->resolveMailer($sender))->html($html, function ($message) use ($to, $subject, $fromEmail, $fromName, $replyTo, $cc, $bcc) {
            $message->to($to)->subject($subject);
            if ($fromEmail) {
                $message->from($fromEmail, $fromName ?: null);
            }
            if ($replyTo) {
                $message->replyTo($replyTo);
            }
            if ($cc) {
                $message->cc($cc);
            }
            if ($bcc) {
                $message->bcc($bcc);
            }
        });

        return EmailSend::create([
            'event_id' => $template->event_id,
            'template_id' => $template->id,
            'to_email' => $to,
            'subject' => $subject,
            'rendered_html' => $html,
            'merge_data' => $merge,
            'trigger' => $trigger,
            'status' => 'sent',
            'sent_at' => now(),
            'created_at' => now(),
        ]);
    }

    /** The event's Sender Details config (empty array when unset). */
    protected function eventSender(?int $eventId): array
    {
        if (! $eventId) {
            return [];
        }

        return EventSetting::where('event_id', $eventId)->first()?->sender ?? [];
    }

    /** @return array<int, string> Trimmed, non-empty addresses from a CSV string. */
    protected function splitEmails(?string $csv): array
    {
        if (! $csv) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $csv))));
    }

    /**
     * Register + return an on-the-fly SMTP mailer when the event enabled a custom
     * SMTP server; otherwise null (= the app's default mailer / Mailpit in dev).
     */
    protected function resolveMailer(array $sender): ?string
    {
        if (empty($sender['is_smtp_config']) || empty($sender['mail_host'])) {
            return null;
        }

        config(['mail.mailers.event_smtp' => [
            'transport'  => $sender['mail_mailer'] ?: 'smtp',
            'host'       => $sender['mail_host'],
            'port'       => (int) ($sender['mail_port'] ?: 587),
            'encryption' => $sender['mail_encryption'] ?: null,
            'username'   => $sender['mail_username'] ?: null,
            'password'   => $sender['mail_password'] ?: null,
            'timeout'    => null,
        ]]);

        return 'event_smtp';
    }
}
