<?php

namespace App\Services\Email;

use App\Models\EmailSend;
use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Mail;

/**
 * Compiles templates and sends rendered messages through the mail transport
 * (Mailpit in dev), recording each as an email_send (architecture §6.13).
 */
class EmailDispatcher
{
    public function __construct(protected EmailRenderer $renderer) {}

    /** Render the template's blocks → compiled_html (cached on the template). */
    public function compile(EmailTemplate $template): string
    {
        $blocks = $template->design['blocks'] ?? [];
        $html = $this->renderer->render($blocks);

        $template->update(['compiled_html' => $html]);

        return $html;
    }

    /** Compiled HTML with merge variables applied (no DB write). */
    public function preview(EmailTemplate $template, array $merge = []): string
    {
        $html = $template->compiled_html ?: $this->compile($template);

        return $this->renderer->merge($html, $merge);
    }

    public function send(EmailTemplate $template, string $to, array $merge = [], string $trigger = 'test'): EmailSend
    {
        $subject = $this->renderer->merge($template->subject ?: $template->name, $merge);
        $html = $this->preview($template, $merge);

        Mail::html($html, function ($message) use ($to, $subject, $template) {
            $message->to($to)->subject($subject);
            if ($template->from_email) {
                $message->from($template->from_email, $template->from_name);
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
}
