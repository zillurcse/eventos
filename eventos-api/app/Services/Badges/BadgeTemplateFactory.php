<?php

namespace App\Services\Badges;

use App\Support\BadgeAudience;

/**
 * Starter badge designs, one per audience.
 *
 * An organizer should never face an empty canvas: the first thing they need is
 * a badge that already has the event logo, the person's name, their company and
 * a scannable QR in sensible places, which they then restyle. This builds
 * exactly that.
 *
 * The output is the `badge_json` the canvas editor itself writes — same box
 * shape (`id`/`key`/`text`/`type`/`position`/`properties`), same `page_config`,
 * same `frontBackground` CSS gradient string — so a generated design opens in
 * the editor indistinguishable from a hand-drawn one. Geometry is in the
 * editor's canvas pixels for A6 portrait (397 × 559), which is what
 * page_config's presetWidth/presetHeight of 105 × 148 mm map to.
 */
class BadgeTemplateFactory
{
    private const PAGE_WIDTH = 397;

    private const PAGE_HEIGHT = 559;

    /** Accent gradient per audience, so the four starters are telling apart. */
    private const GRADIENTS = [
        'attendee' => 'linear-gradient(200deg, #2B6CB0, #90CDF4, #FFFFFF, #BEE3F8)',
        'speaker' => 'linear-gradient(200deg, #6B46C1, #D6BCFA, #FFFFFF, #E9D8FD)',
        'exhibitor' => 'linear-gradient(200deg, #2C7A7B, #81E6D9, #FFFFFF, #B2F5EA)',
        'sponsor' => 'linear-gradient(200deg, #B7791F, #F6E05E, #FFFFFF, #FEFCBF)',
        'staff' => 'linear-gradient(200deg, #2D3748, #A0AEC0, #FFFFFF, #E2E8F0)',
        'organizer' => 'linear-gradient(200deg, #2D3748, #A0AEC0, #FFFFFF, #E2E8F0)',
        'guest' => 'linear-gradient(200deg, #9B2C2C, #FEB2B2, #FFFFFF, #FED7D7)',
    ];

    /**
     * The full create payload for one starter, ready to hand to
     * BadgeDesign::create() (minus event/organization/author columns).
     *
     * @return array<string, mixed>
     */
    public function build(BadgeAudience $audience, ?string $guestType = null, ?string $name = null): array
    {
        $label = $guestType ?: $audience->label();

        return [
            'name' => $name ?: "{$label} Badge",
            'badge_for' => $audience->value,
            'format' => 'A6',
            'measurements_type' => 'mm',
            'width' => '105',
            'height' => '148',
            'meta' => $guestType ? ['guest_type' => $guestType] : null,
            'layers' => [],
            'badge_json' => $this->badgeJson($audience, $label),
        ];
    }

    /** @return array<string, mixed> */
    private function badgeJson(BadgeAudience $audience, string $label): array
    {
        // Box ids are millisecond timestamps in the editor; keep them unique and
        // ordered here so re-opening a generated design behaves the same way.
        $id = (int) (microtime(true) * 1000);

        return [
            'activeSide' => 'front',
            'frontBoxes' => [
                $this->avatarBox($id + 1, 'event_logo', 'Event Logo', top: 29, left: 145, size: 106),
                $this->textBox($id + 2, 'event_name', 'Event Name', 'h1', top: 145, height: 40, fontSize: 22, weight: 'bold'),
                $this->qrBox($id + 3, top: 200),
                $this->textBox($id + 4, 'full_name', 'Full Name', 'h1', top: 375, height: 46, fontSize: 24, weight: 'bold'),
                $this->textBox($id + 5, 'designation', 'Designation', 'p', top: 421, height: 30, fontSize: 16),
                $this->textBox($id + 6, 'company', 'Company', 'p', top: 451, height: 30, fontSize: 16),
                // The audience label, styled as the strip along the bottom that
                // gate staff read at a glance.
                $this->textBox($id + 7, 'role_label', $label, 'p', top: 497, height: 42, fontSize: 20, weight: 'bold'),
            ],
            'backBoxes' => [],
            'page_config' => [
                'badgeSize' => 'A6',
                'pageWidth' => self::PAGE_WIDTH,
                'pageHeight' => self::PAGE_HEIGHT,
                'showModal' => false,
                'customWidth' => 220,
                'customHeight' => 200,
                'presetWidth' => 105,
                'presetHeight' => 148,
                'badgeSizePreset' => 'preset',
                'badgeOrientation' => 'portrait',
            ],
            'punchArea' => false,
            'punchLong' => null,
            'punchCircle' => null,
            'backBackground' => null,
            'frontBackground' => self::GRADIENTS[$audience->value],
        ];
    }

    /** @return array<string, mixed> */
    private function textBox(int $id, string $key, string $text, string $type, int $top, int $height, int $fontSize, string $weight = 'normal'): array
    {
        $width = self::PAGE_WIDTH - 20;

        return [
            'id' => $id,
            'key' => $key,
            'text' => $text,
            'type' => $type,
            'label' => $text,
            'zIndex' => 2,
            'visible' => true,
            'position' => ['top' => $top, 'left' => 10],
            'isDragging' => false,
            'isSelected' => false,
            'properties' => [
                'x' => 10,
                'y' => $top,
                'font' => 'Roboto',
                'size' => ['width' => $width, 'height' => $height],
                'text' => $text,
                'color' => '#1a202c',
                'avatar' => [],
                'fontSize' => $fontSize,
                'rotation' => 0,
                'direction' => 'ltr',
                'fillColor' => 'transparent',
                'fontStyle' => 'normal',
                'objectFit' => 'cover',
                'textAlign' => 'center',
                'fontWeight' => $weight,
                'displayOption' => 'both sides',
                'imagePosition' => 'center',
                'textTransform' => 'none',
                'verticalAlign' => 'middle',
                'textDecoration' => 'none',
                'horizontalAlign' => 'center',
                'fillTransparency' => true,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function qrBox(int $id, int $top): array
    {
        $size = 150;

        return [
            'id' => $id,
            'key' => 'qrcode',
            // The renderer overwrites this with the participation uuid; the
            // literal "uuid" is what shows while the design has no person.
            'text' => 'uuid',
            'type' => 'qrcode',
            'label' => 'QR Code',
            'zIndex' => 2,
            'visible' => true,
            'position' => ['top' => $top, 'left' => (int) ((self::PAGE_WIDTH - $size) / 2)],
            'isDragging' => false,
            'isSelected' => false,
            'properties' => [
                'x' => (int) ((self::PAGE_WIDTH - $size) / 2),
                'y' => $top,
                'size' => ['width' => $size, 'height' => $size],
                'text' => 'uuid',
                'color' => 'black',
                'avatar' => [],
                'qrcode' => [
                    'value' => 'QRCode',
                    'radius' => 1,
                    'variant' => 'default',
                    'blackColor' => '#000000',
                    'whiteColor' => 'transparent',
                ],
                'fontSize' => 'Auto',
                'rotation' => 0,
                'direction' => 'ltr',
                'fillColor' => 'transparent',
                'objectFit' => 'cover',
                'textAlign' => 'center',
                'displayOption' => 'both sides',
                'imagePosition' => 'center',
                'fillTransparency' => false,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function avatarBox(int $id, string $key, string $label, int $top, int $left, int $size): array
    {
        return [
            'id' => $id,
            'key' => $key,
            // Filled in with a URL at render time; empty draws nothing.
            'text' => '',
            'type' => 'avatar',
            'label' => $label,
            'zIndex' => 2,
            'visible' => true,
            'position' => ['top' => $top, 'left' => $left],
            'isDragging' => false,
            'isSelected' => false,
            'properties' => [
                'x' => $left,
                'y' => $top,
                'src' => null,
                'size' => ['width' => $size, 'height' => $size],
                'text' => '',
                'color' => 'black',
                'avatar' => [
                    'shape' => 'rounded',
                    'radius' => 14,
                    'showRing' => false,
                    'showBorder' => false,
                ],
                'fontSize' => 'Auto',
                'rotation' => 0,
                'direction' => 'ltr',
                'fillColor' => 'transparent',
                'objectFit' => 'cover',
                'textAlign' => 'center',
                'displayOption' => 'both sides',
                'imagePosition' => 'center',
                'fillTransparency' => false,
            ],
        ];
    }
}
