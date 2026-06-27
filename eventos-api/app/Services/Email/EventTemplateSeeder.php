<?php

namespace App\Services\Email;

use App\Models\EmailTemplate;
use App\Models\Event;
use Illuminate\Support\Str;

/**
 * Seeds the 36 system email templates for a newly created event.
 * All templates use a modern block-based design system with gradient heroes,
 * info cards, profile cards and consistent typography.
 * Idempotent — skips any key that already exists for the event.
 */
class EventTemplateSeeder
{
    public function seedForEvent(Event $event, int $organizationId): void
    {
        foreach ($this->definitions() as $def) {
            $exists = EmailTemplate::where('event_id', $event->id)
                ->where('key', $def['key'])
                ->exists();

            if ($exists) {
                continue;
            }

            EmailTemplate::create([
                'uuid'            => (string) Str::uuid(),
                'organization_id' => $organizationId,
                'event_id'        => $event->id,
                'key'             => $def['key'],
                'name'            => $def['name'],
                'subject'         => $def['subject'],
                'design'          => [
                    'blocks'   => $def['blocks'],
                    'settings' => $def['settings'] ?? $this->settings(),
                ],
                'status'  => 'published',
                'version' => 1,
            ]);
        }
    }

    // ── Design system ────────────────────────────────────────────────────────

    private function uid(): string
    {
        return 'b' . substr(md5(uniqid('', true)), 0, 10);
    }

    private function settings(string $bg = '#f1f5f9'): array
    {
        return [
            'backgroundColor'   => $bg,
            'contentBackground' => '#ffffff',
            'contentWidth'      => 600,
            'fontFamily'        => "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif",
            'textColor'         => '#334155',
            'linkColor'         => '#6352e7',
            'borderRadius'      => 0,
        ];
    }

    /** Full-width gradient hero banner */
    private function hero(string $emoji, string $title, string $subtitle, string $from, string $to): array
    {
        $html = "<div style=\"background:linear-gradient(135deg,{$from} 0%,{$to} 100%);padding:48px 36px;text-align:center\">"
            . ($emoji ? "<div style=\"font-size:40px;margin-bottom:16px\">{$emoji}</div>" : '')
            . "<h1 style=\"margin:0;font-size:28px;font-weight:800;color:#fff;line-height:1.3;letter-spacing:-0.3px\">{$title}</h1>"
            . ($subtitle ? "<p style=\"margin:14px 0 0;font-size:15px;color:rgba(255,255,255,0.85);line-height:1.6;max-width:480px;margin-left:auto;margin-right:auto\">{$subtitle}</p>" : '')
            . '</div>';

        return ['id' => $this->uid(), 'type' => 'html', 'html' => $html, 'style' => ['paddingTop' => 0, 'paddingBottom' => 0]];
    }

    /** Logo block */
    private function logo(string $bg = '#ffffff'): array
    {
        return ['id' => $this->uid(), 'type' => 'logo', 'src' => '', 'alt' => '{{ organization.name }}', 'href' => '', 'style' => ['align' => 'center', 'width' => 130, 'paddingTop' => 24, 'paddingBottom' => 20, 'backgroundColor' => $bg]];
    }

    /** Body text */
    private function body(string $html, string $align = 'left', int $size = 15, string $color = '#334155'): array
    {
        return ['id' => $this->uid(), 'type' => 'text', 'html' => $html, 'style' => ['align' => $align, 'fontSize' => $size, 'lineHeight' => '1.7', 'color' => $color, 'paddingTop' => 12, 'paddingBottom' => 12, 'paddingLeft' => 32, 'paddingRight' => 32]];
    }

    /** CTA button */
    private function button(string $label, string $url = '{{ event.url }}', string $bg = '#6352e7'): array
    {
        return ['id' => $this->uid(), 'type' => 'button', 'text' => $label, 'url' => $url, 'style' => ['align' => 'center', 'backgroundColor' => $bg, 'color' => '#ffffff', 'borderRadius' => 10, 'paddingX' => 32, 'paddingY' => 14, 'fontSize' => 15, 'fontWeight' => '700', 'fullWidth' => false, 'paddingTop' => 8, 'paddingBottom' => 24]];
    }

    /** Thin divider */
    private function divider(): array
    {
        return ['id' => $this->uid(), 'type' => 'divider', 'style' => ['color' => '#e2e8f0', 'height' => 1, 'width' => 100, 'paddingTop' => 4, 'paddingBottom' => 4]];
    }

    /** Spacer */
    private function spacer(int $h = 16): array
    {
        return ['id' => $this->uid(), 'type' => 'spacer', 'style' => ['height' => $h, 'paddingTop' => 0, 'paddingBottom' => 0]];
    }

    /** Social icons row */
    private function social(): array
    {
        return ['id' => $this->uid(), 'type' => 'social', 'items' => [['network' => 'twitter', 'url' => 'https://'], ['network' => 'linkedin', 'url' => 'https://'], ['network' => 'instagram', 'url' => 'https://']], 'style' => ['align' => 'center', 'iconSize' => 24, 'color' => '#94a3b8', 'paddingTop' => 16, 'paddingBottom' => 8]];
    }

    /** Footer text */
    private function footer(): array
    {
        $html = '© {{ system.year }} {{ organization.name }}&nbsp;&nbsp;·&nbsp;&nbsp;<a href="{{ unsubscribe_url }}" style="color:#94a3b8;text-decoration:underline">Unsubscribe</a>';
        return ['id' => $this->uid(), 'type' => 'text', 'html' => $html, 'style' => ['align' => 'center', 'fontSize' => 12, 'color' => '#94a3b8', 'lineHeight' => '1.6', 'paddingTop' => 4, 'paddingBottom' => 24]];
    }

    /** Colored highlight / alert box */
    private function alertBox(string $text, string $accent, string $emoji = 'ℹ️'): array
    {
        $light = $this->hex2rgba($accent, 0.08);
        $html = "<div style=\"background:{$light};border-left:4px solid {$accent};border-radius:0 10px 10px 0;padding:16px 20px;margin:8px 32px;display:flex;align-items:flex-start;gap:12px\">"
            . "<span style=\"font-size:20px;flex-shrink:0;line-height:1.4\">{$emoji}</span>"
            . "<p style=\"margin:0;font-size:14px;color:#334155;line-height:1.65\">{$text}</p>"
            . '</div>';
        return ['id' => $this->uid(), 'type' => 'html', 'html' => $html, 'style' => ['paddingTop' => 4, 'paddingBottom' => 4]];
    }

    /** Event info card: date / time / location */
    private function eventInfoCard(string $accent = '#6352e7'): array
    {
        $light = $this->hex2rgba($accent, 0.06);
        $html = "<div style=\"background:{$light};border:1px solid {$this->hex2rgba($accent,0.15)};border-radius:12px;padding:4px 20px;margin:8px 32px\">"
            . $this->infoRow('📅', 'Date', '{{ event.date }}', $accent)
            . $this->infoRow('🕐', 'Time', '{{ event.timing }}', $accent)
            . $this->infoRow('📍', 'Location', '{{ event.location }}', $accent, false)
            . '</div>';
        return ['id' => $this->uid(), 'type' => 'html', 'html' => $html, 'style' => ['paddingTop' => 4, 'paddingBottom' => 4]];
    }

    /** Meeting detail card */
    private function meetingCard(string $accent = '#6352e7'): array
    {
        $light = $this->hex2rgba($accent, 0.06);
        $html = "<div style=\"background:{$light};border:1px solid {$this->hex2rgba($accent,0.15)};border-radius:12px;padding:4px 20px;margin:8px 32px\">"
            . $this->infoRow('📅', 'Date', '{{ meeting.date }}', $accent)
            . $this->infoRow('🕐', 'Time', '{{ meeting.time }}', $accent)
            . $this->infoRow('📍', 'Location', '{{ meeting.location }}', $accent, false)
            . '</div>';
        return ['id' => $this->uid(), 'type' => 'html', 'html' => $html, 'style' => ['paddingTop' => 4, 'paddingBottom' => 4]];
    }

    /** Session detail card */
    private function sessionCard(string $accent = '#8b5cf6'): array
    {
        $light = $this->hex2rgba($accent, 0.06);
        $html = "<div style=\"background:{$light};border:1px solid {$this->hex2rgba($accent,0.15)};border-radius:12px;padding:4px 20px;margin:8px 32px\">"
            . $this->infoRow('📅', 'Date', '{{ session.date }}', $accent)
            . $this->infoRow('🕐', 'Time', '{{ session.time }}', $accent)
            . $this->infoRow('📍', 'Room', '{{ session.location }}', $accent, false)
            . '</div>';
        return ['id' => $this->uid(), 'type' => 'html', 'html' => $html, 'style' => ['paddingTop' => 4, 'paddingBottom' => 4]];
    }

    /** User profile card */
    private function profileCard(string $accent = '#6352e7', string $name = '{{ other_user.name }}', string $title = '{{ other_user.title }}', string $company = '{{ other_user.company }}'): array
    {
        $bg = $this->hex2rgba($accent, 0.08);
        $border = $this->hex2rgba($accent, 0.18);
        $html = "<div style=\"background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:20px 24px;margin:8px 32px;display:flex;align-items:center;gap:18px\">"
            . "<div style=\"width:56px;height:56px;border-radius:50%;background:{$bg};border:2px solid {$border};flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:24px\">👤</div>"
            . "<div><div style=\"font-size:16px;font-weight:700;color:#0f172a\">{$name}</div>"
            . "<div style=\"font-size:13px;color:#64748b;margin-top:4px\">{$title}</div>"
            . "<div style=\"font-size:13px;color:#94a3b8;margin-top:2px\">{$company}</div></div>"
            . '</div>';
        return ['id' => $this->uid(), 'type' => 'html', 'html' => $html, 'style' => ['paddingTop' => 4, 'paddingBottom' => 4]];
    }

    /** Credential / details table */
    private function credBox(array $rows, string $accent = '#6352e7'): array
    {
        $items = '';
        $last = count($rows) - 1;
        foreach ($rows as $i => [$label, $value]) {
            $border = $i < $last ? 'border-bottom:1px solid #e2e8f0;' : '';
            $items .= "<div style=\"display:flex;justify-content:space-between;align-items:center;padding:13px 0;{$border}\">"
                . "<span style=\"font-size:13px;color:#64748b\">{$label}</span>"
                . "<span style=\"font-size:13px;font-weight:600;color:#0f172a;font-family:monospace;background:{$this->hex2rgba($accent,0.07)};padding:3px 10px;border-radius:6px\">{$value}</span>"
                . '</div>';
        }
        $html = "<div style=\"background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:4px 20px;margin:8px 32px\">{$items}</div>";
        return ['id' => $this->uid(), 'type' => 'html', 'html' => $html, 'style' => ['paddingTop' => 4, 'paddingBottom' => 4]];
    }

    /** OTP code display */
    private function otpBox(): array
    {
        $html = "<div style=\"text-align:center;padding:24px 32px\">"
            . "<div style=\"display:inline-block;background:#f5f3ff;border:2px solid #6352e7;border-radius:14px;padding:24px 44px\">"
            . "<div style=\"font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:2.5px;color:#6352e7;margin-bottom:12px\">Your Access Code</div>"
            . "<div style=\"font-size:40px;font-weight:800;letter-spacing:14px;color:#0f172a;font-family:'Courier New',monospace\">{{ otp.code }}</div>"
            . "<div style=\"font-size:12px;color:#94a3b8;margin-top:12px\">Expires in 10 minutes · Do not share</div>"
            . '</div></div>';
        return ['id' => $this->uid(), 'type' => 'html', 'html' => $html, 'style' => ['paddingTop' => 0, 'paddingBottom' => 0]];
    }

    /** Stats / badge row */
    private function statsBadge(string $label, string $value, string $accent): array
    {
        $light = $this->hex2rgba($accent, 0.09);
        $html = "<div style=\"text-align:center;padding:20px 32px\">"
            . "<div style=\"display:inline-block;background:{$light};border-radius:50px;padding:10px 28px\">"
            . "<span style=\"font-size:13px;color:{$accent};font-weight:600\">{$label}:</span>"
            . "<span style=\"font-size:15px;font-weight:800;color:{$accent};margin-left:8px\">{$value}</span>"
            . '</div></div>';
        return ['id' => $this->uid(), 'type' => 'html', 'html' => $html, 'style' => ['paddingTop' => 0, 'paddingBottom' => 0]];
    }

    private function infoRow(string $emoji, string $label, string $value, string $accent, bool $border = true): string
    {
        $b = $border ? 'border-bottom:1px solid ' . $this->hex2rgba($accent, 0.12) . ';' : '';
        return "<div style=\"display:flex;align-items:center;gap:14px;padding:14px 0;{$b}\">"
            . "<span style=\"font-size:20px;width:28px;text-align:center;flex-shrink:0\">{$emoji}</span>"
            . "<div><div style=\"font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:1px;color:#94a3b8;margin-bottom:3px\">{$label}</div>"
            . "<div style=\"font-size:15px;font-weight:600;color:#0f172a\">{$value}</div></div>"
            . '</div>';
    }

    private function hex2rgba(string $hex, float $alpha): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) $hex = str_repeat(substr($hex, 0, 1), 2) . str_repeat(substr($hex, 1, 1), 2) . str_repeat(substr($hex, 2, 1), 2);
        [$r, $g, $b] = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
        return "rgba({$r},{$g},{$b},{$alpha})";
    }

    // ── Template definitions ─────────────────────────────────────────────────

    private function definitions(): array
    {
        return [

            // ══════════════════════════════════════════════════════
            // ADMIN
            // ══════════════════════════════════════════════════════

            [
                'key'     => 'admin.event_ready',
                'name'    => 'Your Event is Ready',
                'subject' => 'Your Event is Ready | {{ event.name }}',
                'settings' => $this->settings('#eef2ff'),
                'blocks'  => [
                    $this->logo('#6352e7'),
                    $this->hero('🎉', "Your Event is Ready", 'Everything is set up and waiting for you — start building an unforgettable experience.', '#6352e7', '#4f46e5'),
                    $this->body('Dear <strong>{{ organizer.name }}</strong>,<br><br>Your event <strong>{{ event.name }}</strong> has been successfully created. You now have everything you need — registrations, networking, sessions, and lead generation — all in one place.'),
                    $this->credBox([
                        ['Dashboard URL', '{{ event.url }}'],
                        ['Username', '{{ event.username }}'],
                        ['Password', '{{ event.password }}'],
                    ], '#6352e7'),
                    $this->body('Log in and start customising your event. Your audience is waiting.', 'center'),
                    $this->button('Access My Dashboard', '{{ event.url }}', '#6352e7'),
                    $this->divider(),
                    $this->social(),
                    $this->footer(),
                ],
            ],

            [
                'key'     => 'admin.event_live',
                'name'    => "You're Now Live",
                'subject' => "You're Now Live | {{ event.name }}",
                'settings' => $this->settings('#f0fdf4'),
                'blocks'  => [
                    $this->logo('#16a34a'),
                    $this->hero('🚀', "You're Live!", 'Your event is now visible to the world. Time to drive registrations and build momentum.', '#16a34a', '#15803d'),
                    $this->body('Dear <strong>{{ organizer.name }}</strong>,<br><br>Your event <strong>{{ event.name }}</strong> is now live and visible to participants. They can explore, register, and engage immediately.'),
                    $this->alertBox('Share your event link and start promoting across your channels to maximise reach.', '#16a34a', '📣'),
                    $this->button('View Live Event', '{{ event.url }}', '#16a34a'),
                    $this->divider(),
                    $this->social(),
                    $this->footer(),
                ],
            ],

            [
                'key'     => 'admin.event_offline',
                'name'    => 'Event is Offline',
                'subject' => 'Event is Offline | {{ event.name }}',
                'settings' => $this->settings('#fffbeb'),
                'blocks'  => [
                    $this->logo('#f59e0b'),
                    $this->hero('⚠️', 'Event is Offline', 'Your event is currently not visible to participants.', '#f59e0b', '#d97706'),
                    $this->body('Dear <strong>{{ organizer.name }}</strong>,<br><br>Your event <strong>{{ event.name }}</strong> has been taken offline. Participants can no longer access or view it. You can republish at any time to restore visibility.'),
                    $this->alertBox('If this was unintentional, head to your dashboard and republish your event immediately.', '#f59e0b', '⚡'),
                    $this->button('Republish Event', '{{ event.url }}', '#f59e0b'),
                    $this->divider(),
                    $this->footer(),
                ],
            ],

            [
                'key'     => 'admin.event_deleted',
                'name'    => 'Event Removed',
                'subject' => 'Event Removed | {{ event.name }}',
                'settings' => $this->settings('#fef2f2'),
                'blocks'  => [
                    $this->logo('#dc2626'),
                    $this->hero('🗑️', 'Event Removed', 'Your event has been permanently deleted.', '#dc2626', '#b91c1c'),
                    $this->body('Dear <strong>{{ organizer.name }}</strong>,<br><br>Your event <strong>{{ event.name }}</strong> has been removed successfully from the platform.'),
                    $this->alertBox('If this was unintentional, please contact our support team as soon as possible. Deleted events may not be recoverable.', '#dc2626', '🆘'),
                    $this->button('Contact Support', 'mailto:support@example.com', '#64748b'),
                    $this->divider(),
                    $this->footer(),
                ],
            ],

            [
                'key'     => 'admin.subscription_expiring',
                'name'    => 'Subscription Expiring Soon',
                'subject' => 'Subscription Expiring Soon | {{ event.name }}',
                'settings' => $this->settings('#fffbeb'),
                'blocks'  => [
                    $this->logo('#f59e0b'),
                    $this->hero('⏳', 'Subscription Expiring Soon', 'Renew now to keep your event running without interruption.', '#f59e0b', '#d97706'),
                    $this->body('Dear <strong>{{ organizer.name }}</strong>,<br><br>Your subscription for <strong>{{ event.name }}</strong> is about to expire. Once it lapses, participants will lose access to the event platform.'),
                    $this->alertBox('Renew before your subscription ends to avoid any disruption to your event and attendees.', '#f59e0b', '⚡'),
                    $this->button('Renew Subscription', '{{ billing.url }}', '#f59e0b'),
                    $this->divider(),
                    $this->footer(),
                ],
            ],

            [
                'key'     => 'admin.subscription_renewed',
                'name'    => 'Subscription Renewed',
                'subject' => 'Subscription Renewed | {{ event.name }}',
                'settings' => $this->settings('#f0fdf4'),
                'blocks'  => [
                    $this->logo('#16a34a'),
                    $this->hero('✅', 'Subscription Renewed', 'Your access continues — no interruption, no downtime.', '#16a34a', '#15803d'),
                    $this->body('Dear <strong>{{ organizer.name }}</strong>,<br><br>Your subscription for <strong>{{ event.name }}</strong> has been renewed successfully. All features remain active and your event continues without interruption.'),
                    $this->button('Go to Dashboard', '{{ event.url }}', '#16a34a'),
                    $this->divider(),
                    $this->social(),
                    $this->footer(),
                ],
            ],

            [
                'key'     => 'admin.plan_upgraded',
                'name'    => 'Plan Upgraded Successfully',
                'subject' => 'Plan Upgraded Successfully | {{ event.name }}',
                'settings' => $this->settings('#faf5ff'),
                'blocks'  => [
                    $this->logo('#7c3aed'),
                    $this->hero('🎯', 'Plan Upgraded!', 'You now have access to powerful new features for {{ event.name }}.', '#7c3aed', '#6d28d9'),
                    $this->body('Dear <strong>{{ organizer.name }}</strong>,<br><br>Your plan has been upgraded successfully. Explore your new features and unlock the full potential of your event.'),
                    $this->alertBox('Head to your dashboard to discover everything your new plan unlocks.', '#7c3aed', '✨'),
                    $this->button('Explore New Features', '{{ event.url }}', '#7c3aed'),
                    $this->divider(),
                    $this->social(),
                    $this->footer(),
                ],
            ],

            // ══════════════════════════════════════════════════════
            // REGISTRATION & ACCESS
            // ══════════════════════════════════════════════════════

            [
                'key'     => 'registration.confirmed',
                'name'    => 'Registration Confirmed',
                'subject' => 'Registration Confirmed | {{ event.name }}',
                'settings' => $this->settings('#eff6ff'),
                'blocks'  => [
                    $this->logo('#0ea5e9'),
                    $this->hero('🎟️', "You're officially in!", 'Your registration for {{ event.name }} is confirmed.', '#0ea5e9', '#0284c7'),
                    $this->body('Hi <strong>{{ contact.first_name }}</strong>,<br><br>We\'re thrilled to have you! Your spot is secured. Here\'s everything you need to know before the event:'),
                    $this->eventInfoCard('#0ea5e9'),
                    $this->body('Plan your schedule, explore sessions, and start connecting with other participants before the event kicks off.', 'center'),
                    $this->button('Plan My Experience', '{{ event.url }}', '#0ea5e9'),
                    $this->divider(),
                    $this->social(),
                    $this->footer(),
                ],
            ],

            [
                'key'     => 'registration.pass_ready',
                'name'    => 'Your Pass is Ready',
                'subject' => 'Your Pass is Ready | {{ event.name }}',
                'settings' => $this->settings('#faf5ff'),
                'blocks'  => [
                    $this->logo('#6352e7'),
                    $this->hero('🎫', 'Your Pass is Ready', 'Download your event pass before arrival — you\'ll need it for check-in.', '#6352e7', '#4f46e5'),
                    $this->body('Hi <strong>{{ user.name }}</strong>,<br><br>Your event pass for <strong>{{ event.name }}</strong> is ready and waiting. Download it now and keep it accessible on your phone or print it for smooth entry at the venue.'),
                    $this->alertBox('Have your pass ready when you arrive — it\'s your key to a seamless check-in experience.', '#6352e7', '💡'),
                    $this->button('Download My Pass', '{{ badge.download_url }}', '#6352e7'),
                    $this->divider(),
                    $this->social(),
                    $this->footer(),
                ],
            ],

            [
                'key'     => 'registration.welcome',
                'name'    => 'Welcome to the Experience',
                'subject' => 'Welcome to {{ event.name }}',
                'settings' => $this->settings('#f0fdf4'),
                'blocks'  => [
                    $this->logo('#10b981'),
                    $this->hero('👋', 'Welcome to {{ event.name }}!', 'Start exploring, connecting, and making every moment count.', '#10b981', '#059669'),
                    $this->body('Hi <strong>{{ user.first_name }}</strong>,<br><br>Welcome aboard! You\'re now part of <strong>{{ event.name }}</strong>. The platform is ready for you — browse sessions, connect with participants, and make the most of every opportunity.'),
                    $this->button('Start Exploring', '{{ event.url }}', '#10b981'),
                    $this->divider(),
                    $this->social(),
                    $this->footer(),
                ],
            ],

            [
                'key'     => 'auth.otp',
                'name'    => 'Your Secure Access Code',
                'subject' => 'Your Access Code | {{ event.name }}',
                'settings' => $this->settings('#faf5ff'),
                'blocks'  => [
                    $this->logo('#6352e7'),
                    $this->hero('🔐', 'Your Secure Access Code', 'Use the code below to log in to your event experience.', '#6352e7', '#4f46e5'),
                    $this->body('Hi <strong>{{ user.name }}</strong>,<br><br>We\'re glad to have you with us. Use the one-time code below to access <strong>{{ event.name }}</strong>. This code is valid for 10 minutes.'),
                    $this->otpBox(),
                    $this->alertBox('Never share your access code with anyone. Our team will never ask for it.', '#64748b', '🔒'),
                    $this->divider(),
                    $this->footer(),
                ],
            ],

            [
                'key'     => 'auth.password_updated',
                'name'    => 'Password Updated Successfully',
                'subject' => 'Password Updated | {{ event.name }}',
                'settings' => $this->settings('#f0fdf4'),
                'blocks'  => [
                    $this->logo('#16a34a'),
                    $this->hero('🔒', 'Password Updated', 'Your account password has been changed successfully.', '#16a34a', '#15803d'),
                    $this->body('Hi <strong>{{ user.name }}</strong>,<br><br>Your password for <strong>{{ event.name }}</strong> has been updated. If you made this change, no further action is needed.'),
                    $this->alertBox('If you did <strong>not</strong> change your password, contact support immediately — your account may be at risk.', '#dc2626', '⚠️'),
                    $this->button('Contact Support', 'mailto:support@example.com', '#64748b'),
                    $this->divider(),
                    $this->footer(),
                ],
            ],

            // ══════════════════════════════════════════════════════
            // ONBOARDING
            // ══════════════════════════════════════════════════════

            [
                'key'     => 'onboarding.complete',
                'name'    => "You're All Set",
                'subject' => "You're All Set | {{ event.name }}",
                'settings' => $this->settings('#f0fdf4'),
                'blocks'  => [
                    $this->logo('#10b981'),
                    $this->hero('🎉', "You're All Set!", 'Your profile is complete and you\'re ready to engage at {{ event.name }}.', '#10b981', '#059669'),
                    $this->body('Hi <strong>{{ user.first_name }}</strong>,<br><br>Great news — your onboarding is complete! Your profile is live and visible to other participants. Start exploring, scheduling meetings, and building meaningful connections.'),
                    $this->button('Start Engaging', '{{ event.url }}', '#10b981'),
                    $this->divider(),
                    $this->social(),
                    $this->footer(),
                ],
            ],

            [
                'key'     => 'onboarding.incomplete',
                'name'    => 'Complete Your Registration',
                'subject' => 'One Step Left | {{ event.name }}',
                'settings' => $this->settings('#fffbeb'),
                'blocks'  => [
                    $this->logo('#f59e0b'),
                    $this->hero('⚡', 'Almost There!', 'You started your registration for {{ event.name }} — just one step left.', '#f59e0b', '#d97706'),
                    $this->body('Hi <strong>{{ user.first_name }}</strong>,<br><br>You\'re so close! Complete your profile to unlock the full event experience — from networking and meetings to sessions and the event feed.'),
                    $this->alertBox('Only participants with complete profiles can be discovered and contacted by others.', '#f59e0b', '💡'),
                    $this->button('Complete My Registration', '{{ event.url }}', '#f59e0b'),
                    $this->divider(),
                    $this->social(),
                    $this->footer(),
                ],
            ],

            // ══════════════════════════════════════════════════════
            // EVENT LIFECYCLE
            // ══════════════════════════════════════════════════════

            [
                'key'     => 'event.details',
                'name'    => 'Event Details & Information',
                'subject' => 'Event Details | {{ event.name }}',
                'settings' => $this->settings('#eff6ff'),
                'blocks'  => [
                    $this->logo('#0ea5e9'),
                    $this->hero('📋', 'Your Event Details', 'Everything you need to know about {{ event.name }}.', '#0ea5e9', '#0284c7'),
                    $this->body('Hi <strong>{{ user.first_name }}</strong>,<br><br>We\'re delighted to have you at <strong>{{ event.name }}</strong>. Get ready to connect, explore sessions, and make the most of every opportunity.'),
                    $this->eventInfoCard('#0ea5e9'),
                    $this->body('Explore the agenda, schedule meetings with attendees, and plan your sessions ahead of time.', 'center'),
                    $this->button('Explore the Event', '{{ event.url }}', '#0ea5e9'),
                    $this->divider(),
                    $this->social(),
                    $this->footer(),
                ],
            ],

            [
                'key'     => 'event.starts_soon',
                'name'    => 'Event Starts Soon',
                'subject' => '{{ event.name }} Starts Soon',
                'settings' => $this->settings('#fffbeb'),
                'blocks'  => [
                    $this->logo('#f59e0b'),
                    $this->hero('⏰', 'Get Ready — Starts Soon!', '{{ event.name }} is just around the corner. Make sure you\'re prepared.', '#f59e0b', '#d97706'),
                    $this->body('Hi <strong>{{ user.first_name }}</strong>,<br><br>The event is approaching fast. Now is the perfect time to finalise your schedule, book remaining meetings, and review the agenda so you don\'t miss a thing.'),
                    $this->eventInfoCard('#f59e0b'),
                    $this->button('Plan Ahead Now', '{{ event.url }}', '#f59e0b'),
                    $this->divider(),
                    $this->social(),
                    $this->footer(),
                ],
            ],

            [
                'key'     => 'event.live',
                'name'    => "We're Live",
                'subject' => "We're Live Now | {{ event.name }}",
                'settings' => $this->settings('#fef2f2'),
                'blocks'  => [
                    $this->logo('#ef4444'),
                    $this->hero('🔴', "We're Live — Join Now!", 'The event has started. Everything is ready for you.', '#ef4444', '#dc2626'),
                    $this->body('Hi <strong>{{ user.first_name }}</strong>,<br><br>Welcome to <strong>{{ event.name }}</strong>! The platform is now live and buzzing with activity. Jump in, connect with participants, join sessions, and make every moment count.'),
                    $this->eventInfoCard('#ef4444'),
                    $this->button('Join Now', '{{ event.url }}', '#ef4444'),
                    $this->divider(),
                    $this->social(),
                    $this->footer(),
                ],
            ],

            [
                'key'     => 'event.missed_today',
                'name'    => 'We Missed You Today',
                'subject' => "Don't Miss Tomorrow | {{ event.name }}",
                'settings' => $this->settings('#f8fafc'),
                'blocks'  => [
                    $this->logo('#64748b'),
                    $this->hero('😔', 'We Missed You Today', 'You weren\'t at {{ event.name }} today — but tomorrow is still full of opportunity.', '#64748b', '#475569'),
                    $this->body('Hi <strong>{{ user.first_name }}</strong>,<br><br>We didn\'t see you today — and we missed you! The event continues and there\'s plenty still to come. Join us tomorrow and catch everything you\'ve been looking forward to.'),
                    $this->eventInfoCard('#64748b'),
                    $this->button("Join Tomorrow", '{{ event.url }}', '#64748b'),
                    $this->divider(),
                    $this->social(),
                    $this->footer(),
                ],
            ],

            // ══════════════════════════════════════════════════════
            // ENGAGEMENT
            // ══════════════════════════════════════════════════════

            [
                'key'     => 'engagement.profile_viewed',
                'name'    => 'Your Profile Was Viewed',
                'subject' => 'Someone Viewed Your Profile | {{ event.name }}',
                'settings' => $this->settings('#fdf4ff'),
                'blocks'  => [
                    $this->logo('#ec4899'),
                    $this->hero('👀', 'Someone is Interested in You', 'A participant just viewed your profile at {{ event.name }}.', '#ec4899', '#db2777'),
                    $this->body('Hi <strong>{{ user.name }}</strong>,<br><br>You\'ve caught someone\'s attention! A participant browsed your profile — this could be the beginning of a valuable connection. Don\'t wait — reach out now.'),
                    $this->profileCard('#ec4899'),
                    $this->button('Connect Now', '{{ event.url }}', '#ec4899'),
                    $this->divider(),
                    $this->social(),
                    $this->footer(),
                ],
            ],

            [
                'key'     => 'engagement.profile_saved',
                'name'    => 'Your Profile Was Saved',
                'subject' => 'Someone Saved Your Profile | {{ event.name }}',
                'settings' => $this->settings('#fdf4ff'),
                'blocks'  => [
                    $this->logo('#ec4899'),
                    $this->hero('🔖', 'Someone Saved Your Profile', 'A participant bookmarked you at {{ event.name }} — strong interest!', '#ec4899', '#db2777'),
                    $this->body('Hi <strong>{{ user.name }}</strong>,<br><br>Your profile has been saved by another participant at <strong>{{ event.name }}</strong>. This is a clear signal of interest. Make the first move and start a conversation.'),
                    $this->profileCard('#ec4899'),
                    $this->button('Reach Out Now', '{{ event.url }}', '#ec4899'),
                    $this->divider(),
                    $this->social(),
                    $this->footer(),
                ],
            ],

            [
                'key'     => 'engagement.people_match',
                'name'    => 'People You Should Meet',
                'subject' => 'Recommended Connections | {{ event.name }}',
                'settings' => $this->settings('#eff6ff'),
                'blocks'  => [
                    $this->logo('#0ea5e9'),
                    $this->hero('🤝', 'People You Should Meet', 'We found participants at {{ event.name }} who match your interests.', '#0ea5e9', '#0284c7'),
                    $this->body('Hi <strong>{{ user.name }}</strong>,<br><br>Based on your profile and goals, we\'ve identified participants at <strong>{{ event.name }}</strong> who are well-aligned with what you\'re looking for. Check your recommendations and start connecting.'),
                    $this->alertBox('The best connections happen early. Don\'t wait — browse your matches before their schedules fill up.', '#0ea5e9', '💡'),
                    $this->button('View My Recommendations', '{{ event.url }}', '#0ea5e9'),
                    $this->divider(),
                    $this->social(),
                    $this->footer(),
                ],
            ],

            [
                'key'     => 'engagement.new_comment',
                'name'    => 'New Comment on Your Post',
                'subject' => 'New Comment on Your Post | {{ event.name }}',
                'settings' => $this->settings('#fdf4ff'),
                'blocks'  => [
                    $this->logo('#a855f7'),
                    $this->hero('💬', 'Someone Commented on Your Post', 'Keep the conversation going at {{ event.name }}.', '#a855f7', '#9333ea'),
                    $this->body('Hi <strong>{{ user.name }}</strong>,<br><br>Your post is generating engagement! A participant left a comment and is waiting for your reply. Jump in and keep the conversation alive.'),
                    $this->profileCard('#a855f7'),
                    $this->button('Reply Now', '{{ event.url }}', '#a855f7'),
                    $this->divider(),
                    $this->social(),
                    $this->footer(),
                ],
            ],

            [
                'key'     => 'engagement.post_live',
                'name'    => 'Your Post is Now Live',
                'subject' => 'Your Post is Live | {{ event.name }}',
                'settings' => $this->settings('#f0fdf4'),
                'blocks'  => [
                    $this->logo('#10b981'),
                    $this->hero('✅', 'Your Post is Live!', 'Your content is approved and visible to all participants.', '#10b981', '#059669'),
                    $this->body('Hi <strong>{{ user.name }}</strong>,<br><br>Your post has been approved and is now live on the event feed at <strong>{{ event.name }}</strong>. Participants can see it, like it, and comment. Check it out and engage with the community!'),
                    $this->button('View My Post', '{{ event.url }}', '#10b981'),
                    $this->divider(),
                    $this->social(),
                    $this->footer(),
                ],
            ],

            [
                'key'     => 'engagement.new_message',
                'name'    => 'New Message Received',
                'subject' => 'New Message | {{ event.name }}',
                'settings' => $this->settings('#eff6ff'),
                'blocks'  => [
                    $this->logo('#0ea5e9'),
                    $this->hero('✉️', 'You Have a New Message', 'Someone reached out to you at {{ event.name }}.', '#0ea5e9', '#0284c7'),
                    $this->body('Hi <strong>{{ user.name }}</strong>,<br><br>You\'ve received a new message from a fellow participant. Don\'t keep them waiting — reply now and continue the conversation.'),
                    $this->profileCard('#0ea5e9'),
                    $this->button('Reply Now', '{{ event.url }}', '#0ea5e9'),
                    $this->divider(),
                    $this->social(),
                    $this->footer(),
                ],
            ],

            // ══════════════════════════════════════════════════════
            // MEETINGS
            // ══════════════════════════════════════════════════════

            [
                'key'     => 'meeting.request_sent',
                'name'    => 'Meeting Request Sent',
                'subject' => 'Meeting Request Sent | {{ event.name }}',
                'settings' => $this->settings('#eff6ff'),
                'blocks'  => [
                    $this->logo('#0ea5e9'),
                    $this->hero('📅', 'Meeting Request Sent', 'Your request has been submitted — sit tight!', '#0ea5e9', '#0284c7'),
                    $this->body('Hi <strong>{{ user.name }}</strong>,<br><br>Your meeting request with <strong>{{ other_user.name }}</strong> at <strong>{{ event.name }}</strong> has been sent. You\'ll receive a notification as soon as they respond.'),
                    $this->profileCard('#0ea5e9'),
                    $this->meetingCard('#0ea5e9'),
                    $this->body('While you wait, keep exploring other participants and building your network.', 'center'),
                    $this->button('Explore More Participants', '{{ event.url }}', '#0ea5e9'),
                    $this->divider(),
                    $this->social(),
                    $this->footer(),
                ],
            ],

            [
                'key'     => 'meeting.confirmed',
                'name'    => 'Meeting Confirmed',
                'subject' => 'Meeting Confirmed | {{ event.name }}',
                'settings' => $this->settings('#f0fdf4'),
                'blocks'  => [
                    $this->logo('#16a34a'),
                    $this->hero('✅', 'Meeting Confirmed!', 'Your meeting at {{ event.name }} is locked in.', '#16a34a', '#15803d'),
                    $this->body('Hi <strong>{{ user.name }}</strong>,<br><br>Great news — your meeting has been confirmed. Mark your calendar and make sure you\'re ready on time.'),
                    $this->profileCard('#16a34a'),
                    $this->meetingCard('#16a34a'),
                    $this->button('View My Meetings', '{{ event.url }}', '#16a34a'),
                    $this->divider(),
                    $this->social(),
                    $this->footer(),
                ],
            ],

            [
                'key'     => 'meeting.starts_soon',
                'name'    => 'Meeting Starts Soon',
                'subject' => 'Meeting Starts Soon | {{ event.name }}',
                'settings' => $this->settings('#fffbeb'),
                'blocks'  => [
                    $this->logo('#f59e0b'),
                    $this->hero('⏰', 'Your Meeting Starts Soon!', 'Get ready — your meeting at {{ event.name }} is about to begin.', '#f59e0b', '#d97706'),
                    $this->body('Hi <strong>{{ user.name }}</strong>,<br><br>Your meeting with <strong>{{ other_user.name }}</strong> is starting shortly. Make sure you\'re in position and ready to make the most of your time together.'),
                    $this->profileCard('#f59e0b'),
                    $this->meetingCard('#f59e0b'),
                    $this->button('Join Meeting', '{{ meeting.join_url }}', '#16a34a'),
                    $this->divider(),
                    $this->social(),
                    $this->footer(),
                ],
            ],

            [
                'key'     => 'meeting.declined',
                'name'    => 'Meeting Request Declined',
                'subject' => 'Meeting Request Declined | {{ event.name }}',
                'settings' => $this->settings('#f8fafc'),
                'blocks'  => [
                    $this->logo('#64748b'),
                    $this->hero('😞', 'Meeting Request Declined', 'No worries — there are plenty more connections waiting for you.', '#64748b', '#475569'),
                    $this->body('Hi <strong>{{ user.name }}</strong>,<br><br>Unfortunately, <strong>{{ other_user.name }}</strong> was unable to accept your meeting request at the requested time. This happens — don\'t let it slow you down.'),
                    $this->alertBox('There are dozens of other valuable participants at {{ event.name }} waiting to connect. Keep networking!', '#6352e7', '💡'),
                    $this->button('Discover Other Participants', '{{ event.url }}', '#6352e7'),
                    $this->divider(),
                    $this->social(),
                    $this->footer(),
                ],
            ],

            [
                'key'     => 'meeting.room_ready',
                'name'    => 'Your Meeting Room is Ready',
                'subject' => 'Meeting Room Ready | {{ event.name }}',
                'settings' => $this->settings('#f0fdf4'),
                'blocks'  => [
                    $this->logo('#16a34a'),
                    $this->hero('🟢', 'Your Meeting Room is Ready!', 'Your room is live and waiting — join now.', '#16a34a', '#15803d'),
                    $this->body('Hi <strong>{{ user.name }}</strong>,<br><br>Your meeting with <strong>{{ other_user.name }}</strong> is now active. The room is ready and waiting — join immediately and start your conversation.'),
                    $this->profileCard('#16a34a'),
                    $this->button('Join Meeting Now', '{{ meeting.join_url }}', '#16a34a'),
                    $this->divider(),
                    $this->footer(),
                ],
            ],

            // ══════════════════════════════════════════════════════
            // SESSIONS
            // ══════════════════════════════════════════════════════

            [
                'key'     => 'session.starts_soon',
                'name'    => 'Session Starting Soon',
                'subject' => 'Session Starting Soon | {{ event.name }}',
                'settings' => $this->settings('#faf5ff'),
                'blocks'  => [
                    $this->logo('#8b5cf6'),
                    $this->hero('🎤', 'Your Session Starts Soon!', 'A session at {{ event.name }} is about to begin — don\'t miss it.', '#8b5cf6', '#7c3aed'),
                    $this->body('Hi <strong>{{ speaker.name }}</strong>,<br><br>A session you\'re involved in at <strong>{{ event.name }}</strong> is starting shortly. Head to the room and get ready — the audience is waiting!'),
                    $this->sessionCard('#8b5cf6'),
                    $this->button('Join Session Now', '{{ session.url }}', '#8b5cf6'),
                    $this->divider(),
                    $this->social(),
                    $this->footer(),
                ],
            ],

            // ══════════════════════════════════════════════════════
            // LEADS & PERFORMANCE
            // ══════════════════════════════════════════════════════

            [
                'key'     => 'lead.new_contact',
                'name'    => 'New Contact Shared With You',
                'subject' => 'New Contact Shared | {{ event.name }}',
                'settings' => $this->settings('#f0fdfa'),
                'blocks'  => [
                    $this->logo('#14b8a6'),
                    $this->hero('📇', 'A New Contact Was Shared With You', 'A participant shared their details with your booth at {{ event.name }}.', '#14b8a6', '#0d9488'),
                    $this->body('Hi <strong>{{ exhibitor.name }}</strong>,<br><br>A participant has shared their contact information with your booth at <strong>{{ event.name }}</strong>. This is a warm lead — reach out quickly while the connection is fresh.'),
                    $this->profileCard('#14b8a6', '{{ contact.name }}', '{{ contact.title }}', '{{ contact.company }}'),
                    $this->button('View Contact Details', '{{ event.url }}', '#14b8a6'),
                    $this->divider(),
                    $this->footer(),
                ],
            ],

            // ══════════════════════════════════════════════════════
            // USER ACTIONS
            // ══════════════════════════════════════════════════════

            [
                'key'     => 'action.speaker_bookmarked',
                'name'    => 'Speaker Added to Your List',
                'subject' => 'Speaker Saved | {{ event.name }}',
                'settings' => $this->settings('#f8fafc'),
                'blocks'  => [
                    $this->logo('#64748b'),
                    $this->hero('🔖', 'Speaker Saved!', 'You\'ve bookmarked a speaker at {{ event.name }}.', '#64748b', '#475569'),
                    $this->body('Hi <strong>{{ user.name }}</strong>,<br><br>You\'ve successfully added <strong>{{ speaker.name }}</strong> to your bookmark list at <strong>{{ event.name }}</strong>. Access your saved list anytime to revisit and connect.'),
                    $this->button('View My Bookmarks', '{{ event.url }}', '#6352e7'),
                    $this->divider(),
                    $this->footer(),
                ],
            ],

            [
                'key'     => 'action.notes_saved',
                'name'    => 'Notes Saved Successfully',
                'subject' => 'Notes Saved | {{ event.name }}',
                'settings' => $this->settings('#f8fafc'),
                'blocks'  => [
                    $this->logo('#64748b'),
                    $this->hero('📝', 'Notes Saved!', 'Your notes are safe and ready whenever you need them.', '#64748b', '#475569'),
                    $this->body('Hi <strong>{{ user.name }}</strong>,<br><br>Your notes for <strong>{{ item.name }}</strong> at <strong>{{ event.name }}</strong> have been saved. Stay organised and keep track of your key insights and takeaways.'),
                    $this->button('View My Notes', '{{ event.url }}', '#6352e7'),
                    $this->divider(),
                    $this->footer(),
                ],
            ],

            // ══════════════════════════════════════════════════════
            // EXHIBITOR & SPONSOR
            // ══════════════════════════════════════════════════════

            [
                'key'     => 'exhibitor.booth_approved',
                'name'    => 'Your Booth is Approved',
                'subject' => 'Booth Approved | {{ event.name }}',
                'settings' => $this->settings('#fff7ed'),
                'blocks'  => [
                    $this->logo('#f97316'),
                    $this->hero('🏢', "Your Booth is Approved!", "You're officially part of {{ event.name }}. Time to shine.", '#f97316', '#ea580c'),
                    $this->body('Hi <strong>{{ user.name }}</strong>,<br><br>Congratulations! Your booth has been approved and you are now officially part of <strong>{{ event.name }}</strong>. This is your opportunity to attract visitors, generate leads, and maximise your brand\'s presence.'),
                    $this->credBox([
                        ['Booth Dashboard', '{{ event.url }}'],
                        ['Username', '{{ exhibitor.username }}'],
                        ['Password', '{{ exhibitor.password }}'],
                    ], '#f97316'),
                    $this->alertBox('Complete your booth profile now — participants browse exhibitors before the event begins.', '#f97316', '💡'),
                    $this->button('Set Up My Booth', '{{ event.url }}', '#f97316'),
                    $this->divider(),
                    $this->social(),
                    $this->footer(),
                ],
            ],

            // ══════════════════════════════════════════════════════
            // POST EVENT
            // ══════════════════════════════════════════════════════

            [
                'key'     => 'post_event.thank_you',
                'name'    => 'Thank You for Joining',
                'subject' => 'Thank You for Being Part of It | {{ event.name }}',
                'settings' => $this->settings('#faf5ff'),
                'blocks'  => [
                    $this->logo('#6352e7'),
                    $this->hero('🙏', 'Thank You for Joining!', 'It was wonderful having you at {{ event.name }}.', '#6352e7', '#4f46e5'),
                    $this->body('Hi <strong>{{ user.first_name }}</strong>,<br><br>Thank you for being part of <strong>{{ event.name }}</strong>. We hope it was an engaging and rewarding experience — your participation helped make this event truly special for everyone involved.'),
                    $this->body('Stay connected, continue the conversations you started, and build on the relationships you\'ve created. Until next time!', 'center', 15, '#64748b'),
                    $this->button('Stay Connected', '{{ event.url }}', '#6352e7'),
                    $this->divider(),
                    $this->social(),
                    $this->footer(),
                ],
            ],

            [
                'key'     => 'post_event.feedback',
                'name'    => 'Share Your Feedback',
                'subject' => 'Share Your Feedback | {{ event.name }}',
                'settings' => $this->settings('#fffbeb'),
                'blocks'  => [
                    $this->logo('#f59e0b'),
                    $this->hero('⭐', 'Help Us Do Better', 'Your feedback shapes the future of {{ event.name }}.', '#f59e0b', '#d97706'),
                    $this->body('Hi <strong>{{ user.first_name }}</strong>,<br><br>Thank you for attending <strong>{{ event.name }}</strong>. We\'d love to hear your honest thoughts — your feedback helps us improve and deliver even better experiences next time.'),
                    $this->alertBox('The survey takes less than 2 minutes. Your voice genuinely shapes what we build next.', '#f59e0b', '💬'),
                    $this->button('Take the Survey', '{{ survey_url }}', '#f59e0b'),
                    $this->divider(),
                    $this->social(),
                    $this->footer(),
                ],
            ],

        ];
    }
}
