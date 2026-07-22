<?php

namespace App\Services\Email;

use Illuminate\Support\Str;

/**
 * Renders builder blocks into email-client-safe HTML and merges {{ handlebars }}
 * variables (architecture §6.13).
 *
 * The block contract is flat: each block is { type, ...props, style:{...} }.
 * A `columns` block nests child blocks per column. Pure PHP with no Node/MJML
 * dependency — an MjmlRenderer could replace it without touching callers.
 *
 * What makes the output survive real inboxes, rather than just a browser:
 *
 *  - Table layout with inline styles. Gmail strips <style> on some clients and
 *    ignores anything in <head> when a message is clipped/forwarded.
 *  - "Ghost tables" (`<!--[if mso]>`) around the container and buttons, because
 *    Outlook renders with the Word engine, which ignores max-width and padding
 *    on anchors.
 *  - `mso-line-height-rule:exactly` on text — Word otherwise rounds line-height
 *    up and breaks vertical rhythm.
 *  - A hidden preheader ahead of the body, so the inbox preview shows the line
 *    the author wrote rather than the first words of the header.
 *  - Author HTML passes through HtmlSanitizer *here*, so every path (preview,
 *    preview-draft, compile, send) is covered by one choke point.
 */
class EmailRenderer
{
    /** Sensible defaults for the email canvas, overridable per template. */
    protected array $defaults = [
        'backgroundColor' => '#f1f5f9',
        'contentBackground' => '#ffffff',
        'contentWidth' => 600,
        'fontFamily' => "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif",
        'textColor' => '#334155',
        'linkColor' => '#4338ca',
        'borderRadius' => 12,
    ];

    /** Brand colors for the default social chips (no external icon hosting). */
    protected array $socialColors = [
        'twitter' => '#000000',
        'facebook' => '#1877f2',
        'linkedin' => '#0a66c2',
        'instagram' => '#e4405f',
        'youtube' => '#ff0000',
        'tiktok' => '#010101',
        'website' => '#64748b',
    ];

    /** Guard against a hand-crafted payload nesting columns into a stack overflow. */
    protected const MAX_DEPTH = 6;

    public function __construct(protected HtmlSanitizer $sanitizer) {}

    /**
     * @param  array<int,array<string,mixed>>  $blocks
     * @param  array<string,mixed>  $settings  global canvas settings
     * @param  string|null  $preheader  inbox preview line
     */
    public function render(array $blocks, array $settings = [], ?string $preheader = null): string
    {
        $s = array_merge($this->defaults, array_filter($settings, fn ($v) => $v !== null && $v !== ''));

        $rows = '';
        foreach ($blocks as $block) {
            if (is_array($block)) {
                $rows .= $this->renderBlock($block, $s, 0);
            }
        }

        return $this->wrap($rows, $s, $preheader);
    }

    /** Replace {{ token }} occurrences with escaped values from $vars (dotted paths). */
    public function merge(string $html, array $vars): string
    {
        return preg_replace_callback(
            '/\{\{\s*([\w.]+)\s*\}\}/',
            fn ($m) => e((string) data_get($vars, $m[1], '')),
            $html,
        ) ?? $html;
    }

    // ── block rendering ─────────────────────────────────────────────────────

    /** A top-level block: a full-width row whose cell carries padding + background. */
    protected function renderBlock(array $block, array $s, int $depth): string
    {
        // legacy blocks nested props under `content`; flatten for back-compat.
        $p = isset($block['content']) && is_array($block['content'])
            ? array_merge($block['content'], $block)
            : $block;

        $type = (string) ($block['type'] ?? 'text');
        $style = is_array($block['style'] ?? null) ? $block['style'] : [];

        $inner = $this->inner($type, $p, $style, $s, $depth);

        if ($inner === '') {
            return '';
        }

        // Blocks can opt out of the mobile view (a wide hero, a desktop-only
        // sidebar). The class is paired with a media query in wrap().
        $class = ! empty($style['hideOnMobile']) ? ' class="es-hide-mobile"' : '';

        $cellStyle = $this->padding($style).$this->bg($style['backgroundColor'] ?? null);

        return "<tr{$class}><td style=\"{$cellStyle}\">{$inner}</td></tr>";
    }

    /** The inner HTML of a block (no outer row/padding) — reused inside columns. */
    protected function inner(string $type, array $p, array $style, array $s, int $depth): string
    {
        return match ($type) {
            'heading', 'header' => $this->heading($p, $style, $s),
            'text' => $this->text($p, $style, $s),
            'button' => $this->button($p, $style, $s),
            'image' => $this->image($p, $style),
            'logo' => $this->logo($p, $style),
            'video' => $this->video($p, $style),
            'divider' => $this->divider($style),
            'spacer' => $this->spacer($style),
            'social' => $this->social($p, $style, $s),
            'columns' => $this->columns($p, $style, $s, $depth),
            'html' => $this->sanitizer->clean((string) ($p['html'] ?? '')),
            'footer' => $this->footer($p, $style, $s),
            default => '',
        };
    }

    protected function heading(array $p, array $style, array $s): string
    {
        $text = trim((string) ($p['text'] ?? ''));
        if ($text === '') {
            return '';
        }
        $level = max(1, min(3, (int) ($p['level'] ?? 1)));
        $size = $style['fontSize'] ?? [1 => 28, 2 => 22, 3 => 18][$level];
        $css = $this->typo($style, $s, (int) $size, $style['fontWeight'] ?? '700', $style['color'] ?? '#0f172a')
            .'margin:0;';

        return "<h{$level} style=\"{$css}\">".nl2br(e($text))."</h{$level}>";
    }

    protected function text(array $p, array $style, array $s): string
    {
        // `html` is rich text from the editor — sanitized, never trusted.
        // `text` is the plain fallback for blocks authored before rich text.
        $body = $p['html'] ?? null;
        $body = $body !== null && $body !== ''
            ? $this->sanitizer->clean((string) $body)
            : nl2br(e((string) ($p['text'] ?? '')));

        if (trim(strip_tags((string) $body)) === '' && ! str_contains((string) $body, '<img')) {
            return '';
        }

        $css = $this->typo($style, $s, (int) ($style['fontSize'] ?? 15), $style['fontWeight'] ?? '400', $style['color'] ?? $s['textColor'])
            .'line-height:'.($style['lineHeight'] ?? '1.6').';mso-line-height-rule:exactly;';

        return "<div style=\"{$css}\">{$body}</div>";
    }

    /**
     * A bulletproof button: a one-cell table rather than a padded anchor, since
     * Outlook drops padding on inline elements. `mso-padding-alt` restores the
     * padding Word needs while other clients use the real padding on the anchor.
     */
    protected function button(array $p, array $style, array $s): string
    {
        $text = trim((string) ($p['text'] ?? ''));
        if ($text === '') {
            return '';
        }

        $align = $this->align($style['align'] ?? 'left');
        $bg = (string) ($style['backgroundColor'] ?? $s['linkColor']);
        $color = (string) ($style['color'] ?? '#ffffff');
        $radius = (int) ($style['borderRadius'] ?? 8);
        $px = (int) ($style['paddingX'] ?? 24);
        $py = (int) ($style['paddingY'] ?? 13);
        $size = (int) ($style['fontSize'] ?? 15);
        $fullWidth = ! empty($style['fullWidth']);
        $url = $this->url($p['url'] ?? '#');

        $cell = 'background:'.e($bg).';border-radius:'.$radius.'px;'
            ."mso-padding-alt:{$py}px {$px}px;text-align:center;";

        $anchor = '<a href="'.$url.'" target="_blank" rel="noopener noreferrer" '
            .'style="display:inline-block;padding:'.$py.'px '.$px.'px;'
            .'font-family:'.$s['fontFamily'].';font-size:'.$size.'px;font-weight:600;'
            .'color:'.e($color).';text-decoration:none;border-radius:'.$radius.'px;'
            .($fullWidth ? 'width:100%;' : '').'mso-line-height-rule:exactly;line-height:1;">'
            .e($text).'</a>';

        return '<table role="presentation" border="0" cellpadding="0" cellspacing="0" '
            .($fullWidth ? 'width="100%" ' : '').'style="'.($fullWidth ? 'width:100%;' : '').'margin:'.$this->marginFor($align).'">'
            .'<tr><td style="'.$cell.'">'.$anchor.'</td></tr></table>';
    }

    protected function image(array $p, array $style): string
    {
        $src = $this->url((string) ($p['src'] ?? ''), allowEmpty: true);
        if ($src === '') {
            return '';
        }

        $align = $this->align($style['align'] ?? 'center');
        $width = isset($style['width']) && $style['width'] !== '' ? (int) $style['width'].'%' : '100%';
        $radius = (int) ($style['borderRadius'] ?? 0);

        $img = '<img src="'.$src.'" alt="'.e((string) ($p['alt'] ?? '')).'" '
            .'style="width:'.$width.';max-width:100%;height:auto;display:block;border:0;outline:none;'
            .'text-decoration:none;-ms-interpolation-mode:bicubic;border-radius:'.$radius.'px;">';

        if (! empty($p['href'])) {
            $img = '<a href="'.$this->url($p['href']).'" target="_blank" rel="noopener noreferrer">'.$img.'</a>';
        }

        // display:block kills the descender gap, so centering moves to the wrapper.
        return '<div style="text-align:'.$align.';font-size:0;"><div style="display:inline-block;width:'.$width.';max-width:100%;">'.$img.'</div></div>';
    }

    /** Like an image, but sized in px — a logo should not scale with the column. */
    protected function logo(array $p, array $style): string
    {
        $src = $this->url((string) ($p['src'] ?? ''), allowEmpty: true);
        if ($src === '') {
            return '';
        }

        $align = $this->align($style['align'] ?? 'center');
        $width = (int) ($style['width'] ?? 160);

        $img = '<img src="'.$src.'" alt="'.e((string) ($p['alt'] ?? 'Logo')).'" width="'.$width.'" '
            .'style="width:'.$width.'px;max-width:100%;height:auto;display:inline-block;border:0;outline:none;">';

        if (! empty($p['href'])) {
            $img = '<a href="'.$this->url($p['href']).'" target="_blank" rel="noopener noreferrer">'.$img.'</a>';
        }

        return '<div style="text-align:'.$align.';font-size:0;">'.$img.'</div>';
    }

    /**
     * No client plays video inline, so this is a thumbnail linking out. The play
     * badge is a separate absolutely-positioned element in the editor, but that
     * does not survive mail clients — here it is baked in as a centered overlay
     * row using a background image, degrading to a plain linked thumbnail.
     */
    protected function video(array $p, array $style): string
    {
        $src = $this->url((string) ($p['src'] ?? ''), allowEmpty: true);
        if ($src === '') {
            return '';
        }

        $align = $this->align($style['align'] ?? 'center');
        $radius = (int) ($style['borderRadius'] ?? 8);
        $href = $this->url($p['url'] ?? '#');

        $img = '<img src="'.$src.'" alt="'.e((string) ($p['alt'] ?? 'Watch the video')).'" '
            .'style="width:100%;max-width:100%;height:auto;display:block;border:0;outline:none;border-radius:'.$radius.'px;">';

        return '<div style="text-align:'.$align.';font-size:0;">'
            .'<a href="'.$href.'" target="_blank" rel="noopener noreferrer" style="display:block;text-decoration:none;">'
            .$img
            .'<span style="display:block;margin-top:-44px;text-align:center;">'
            .'<span style="display:inline-block;width:44px;height:44px;line-height:44px;border-radius:22px;'
            .'background:#ffffff;color:#1f2430;font-family:Arial,sans-serif;font-size:16px;">&#9654;</span>'
            .'</span></a></div>';
    }

    protected function divider(array $style): string
    {
        $color = (string) ($style['color'] ?? '#e2e8f0');
        $thickness = max(1, (int) ($style['height'] ?? 1));
        $width = isset($style['width']) && $style['width'] !== '' ? (int) $style['width'].'%' : '100%';

        // A bordered table cell, not <hr> — Outlook gives <hr> its own margins.
        return '<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="'.$width.'" '
            .'style="width:'.$width.';margin:0 auto;"><tr>'
            .'<td style="font-size:0;line-height:0;border-top:'.$thickness.'px solid '.e($color).';">&nbsp;</td>'
            .'</tr></table>';
    }

    protected function spacer(array $style): string
    {
        $h = max(1, (int) ($style['height'] ?? 24));

        return '<div style="height:'.$h.'px;line-height:'.$h.'px;font-size:1px;mso-line-height-rule:exactly;">&nbsp;</div>';
    }

    /**
     * Social links as branded chips. Real icon images are used when the author
     * supplies them (per item, or a base URL of `<network>.png` files); the
     * fallback is a colored rounded cell with the network's initial, which needs
     * no hosted assets and still renders in every client.
     */
    protected function social(array $p, array $style, array $s): string
    {
        $items = array_filter(
            is_array($p['items'] ?? null) ? $p['items'] : [],
            fn ($i) => is_array($i) && ! empty($i['url'])
        );

        if (empty($items)) {
            return '';
        }

        $align = $this->align($style['align'] ?? 'center');
        $size = max(16, (int) ($style['iconSize'] ?? 28));
        $base = rtrim((string) ($s['socialIconBaseUrl'] ?? ''), '/');
        $cells = '';

        foreach ($items as $item) {
            $network = strtolower((string) ($item['network'] ?? 'website'));
            $label = ucfirst($network);
            $href = $this->url((string) $item['url']);

            $iconUrl = ! empty($item['icon_url'])
                ? $this->url((string) $item['icon_url'], allowEmpty: true)
                : ($base !== '' ? e("{$base}/{$network}.png") : '');

            if ($iconUrl !== '') {
                $badge = '<img src="'.$iconUrl.'" alt="'.e($label).'" width="'.$size.'" height="'.$size.'" '
                    .'style="width:'.$size.'px;height:'.$size.'px;display:block;border:0;outline:none;">';
            } else {
                $color = $style['color'] ?? ($this->socialColors[$network] ?? $this->socialColors['website']);
                $badge = '<span style="display:inline-block;width:'.$size.'px;height:'.$size.'px;'
                    .'line-height:'.$size.'px;border-radius:'.(int) ($size / 2).'px;background:'.e((string) $color).';'
                    .'color:#ffffff;font-family:Arial,sans-serif;font-size:'.max(10, (int) ($size * 0.46)).'px;'
                    .'font-weight:700;text-align:center;mso-line-height-rule:exactly;">'
                    .e(strtoupper(mb_substr($label, 0, 1))).'</span>';
            }

            $cells .= '<td style="padding:0 5px;">'
                .'<a href="'.$href.'" target="_blank" rel="noopener noreferrer" title="'.e($label).'" '
                .'style="text-decoration:none;">'.$badge.'</a></td>';
        }

        return '<table role="presentation" border="0" cellpadding="0" cellspacing="0" '
            .'style="margin:'.$this->marginFor($align).'"><tr>'.$cells.'</tr></table>';
    }

    protected function footer(array $p, array $style, array $s): string
    {
        $text = trim((string) ($p['text'] ?? ''));
        if ($text === '') {
            return '';
        }
        $css = $this->typo($style, $s, (int) ($style['fontSize'] ?? 12), '400', $style['color'] ?? '#94a3b8');

        return '<div style="'.$css.'">'.nl2br(e($text)).'</div>';
    }

    /**
     * Multi-column layout. Widths come from `widths` (percentages, one per
     * column) and fall back to an even split. Columns stack on mobile via the
     * `.es-col` media query in wrap(); Outlook ignores that and keeps the table.
     */
    protected function columns(array $p, array $style, array $s, int $depth): string
    {
        $cols = is_array($p['columns'] ?? null) ? $p['columns'] : [];
        $count = count($cols);

        if ($count === 0 || $depth >= self::MAX_DEPTH) {
            return '';
        }

        $gap = (int) ($style['gap'] ?? 16);
        $widths = $this->columnWidths($p['widths'] ?? null, $count);
        $cells = '';

        foreach (array_values($cols) as $i => $children) {
            $children = is_array($children) ? $children : [];
            $body = '';

            foreach ($children as $child) {
                if (! is_array($child)) {
                    continue;
                }

                $cp = isset($child['content']) && is_array($child['content'])
                    ? array_merge($child['content'], $child)
                    : $child;
                $cStyle = is_array($child['style'] ?? null) ? $child['style'] : [];

                $childInner = $this->inner((string) ($child['type'] ?? 'text'), $cp, $cStyle, $s, $depth + 1);

                if ($childInner !== '') {
                    $body .= '<div style="'.$this->padding($cStyle, 6, 0).$this->bg($cStyle['backgroundColor'] ?? null).'">'
                        .$childInner.'</div>';
                }
            }

            $half = (int) ($gap / 2);
            $pad = $i === 0
                ? "padding-right:{$half}px;"
                : ($i === $count - 1 ? "padding-left:{$half}px;" : "padding:0 {$half}px;");

            $cells .= '<td class="es-col" valign="'.e((string) ($style['verticalAlign'] ?? 'top')).'" '
                .'width="'.$widths[$i].'%" style="width:'.$widths[$i].'%;'.$pad.'">'
                .($body !== '' ? $body : '&nbsp;').'</td>';
        }

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" '
            .'style="width:100%;table-layout:fixed;"><tr>'.$cells.'</tr></table>';
    }

    /**
     * Normalize author-supplied column widths to percentages summing to 100.
     * Anything missing or nonsensical falls back to an even split, so a bad
     * `widths` array can never collapse the layout.
     *
     * @return array<int,int>
     */
    protected function columnWidths(mixed $widths, int $count): array
    {
        $even = (int) floor(100 / $count);
        $fallback = array_fill(0, $count, $even);
        $fallback[$count - 1] = 100 - $even * ($count - 1);

        if (! is_array($widths) || count($widths) !== $count) {
            return $fallback;
        }

        $clean = [];
        foreach ($widths as $w) {
            if (! is_numeric($w) || (int) $w < 5) {
                return $fallback;
            }
            $clean[] = (int) $w;
        }

        $total = array_sum($clean);
        if ($total <= 0) {
            return $fallback;
        }

        // Rescale to exactly 100 so rounding drift can't overflow the row.
        $scaled = array_map(fn ($w) => max(5, (int) round($w * 100 / $total)), $clean);
        $scaled[$count - 1] = 100 - array_sum(array_slice($scaled, 0, $count - 1));

        return $scaled[$count - 1] < 5 ? $fallback : $scaled;
    }

    // ── style helpers ───────────────────────────────────────────────────────

    protected function typo(array $style, array $s, int $size, string $weight, string $color): string
    {
        return 'font-family:'.$s['fontFamily'].';font-size:'.$size.'px;font-weight:'.e((string) $weight).';'
            .'color:'.e((string) $color).';text-align:'.$this->align($style['align'] ?? 'left').';';
    }

    /** Only real CSS alignment keywords reach the output. */
    protected function align(mixed $value): string
    {
        return in_array($value, ['left', 'center', 'right'], true) ? $value : 'left';
    }

    /** Centering/right-aligning a table needs margins, not text-align. */
    protected function marginFor(string $align): string
    {
        return match ($align) {
            'center' => '0 auto;',
            'right' => '0 0 0 auto;',
            default => '0;',
        };
    }

    protected function padding(array $style, int $fallback = 16, int $sideFallback = 24): string
    {
        $top = max(0, (int) ($style['paddingTop'] ?? $fallback));
        $bottom = max(0, (int) ($style['paddingBottom'] ?? $fallback));
        $left = max(0, (int) ($style['paddingLeft'] ?? $sideFallback));
        $right = max(0, (int) ($style['paddingRight'] ?? $sideFallback));

        return "padding:{$top}px {$right}px {$bottom}px {$left}px;";
    }

    protected function bg(mixed $color): string
    {
        return is_string($color) && $color !== '' ? 'background-color:'.e($color).';' : '';
    }

    /** Normalize/guard a URL: keep merge tokens intact, default unsafe to #. */
    protected function url(mixed $url, bool $allowEmpty = false): string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return $allowEmpty ? '' : '#';
        }

        if (Str::contains($url, '{{')) {
            return e($url); // a merge token — leave it for the merge pass
        }

        if (Str::startsWith($url, ['http://', 'https://', 'mailto:', 'tel:', '#', '/'])) {
            return e($url);
        }

        // A bare "example.com/x" is a typo for https, but anything carrying a
        // scheme we didn't allow (javascript:, data:) must not be upgraded.
        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $url)) {
            return $allowEmpty ? '' : '#';
        }

        return e('https://'.$url);
    }

    /**
     * The inbox preview line. Padded with zero-width joiners so the client does
     * not spill the start of the body into the preview after the text ends.
     */
    protected function preheader(?string $preheader): string
    {
        $text = trim((string) $preheader);

        if ($text === '') {
            return '';
        }

        return '<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;'
            .'font-size:1px;line-height:1px;color:transparent;opacity:0;">'
            .e(Str::limit($text, 200, ''))
            .str_repeat('&#8199;&#65279;&#847; ', 30)
            .'</div>';
    }

    protected function wrap(string $rows, array $s, ?string $preheader = null): string
    {
        $width = max(320, min(900, (int) $s['contentWidth']));
        $radius = max(0, (int) $s['borderRadius']);
        $bg = e((string) $s['backgroundColor']);
        $contentBg = e((string) $s['contentBackground']);

        $head = '<!doctype html><html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" '
            .'xmlns:o="urn:schemas-microsoft-com:office:office" lang="en"><head>'
            .'<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<meta http-equiv="X-UA-Compatible" content="IE=edge">'
            // Tell clients we handle both schemes, so they don't force-invert.
            .'<meta name="color-scheme" content="light dark">'
            .'<meta name="supported-color-schemes" content="light dark">'
            .'<title>&nbsp;</title>'
            // Word needs DPI pinned or images scale up on high-DPI Windows.
            .'<!--[if mso]><noscript><xml><o:OfficeDocumentSettings>'
            .'<o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript><![endif]-->'
            .'<style>'
            .'body{margin:0;padding:0;width:100%!important;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;}'
            .'table{border-collapse:collapse;mso-table-lspace:0;mso-table-rspace:0;}'
            .'img{border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic;}'
            .'a{color:'.e((string) $s['linkColor']).';}'
            // Apple/iOS auto-links dates and addresses in its own blue; neutralize.
            .'a[x-apple-data-detectors]{color:inherit!important;text-decoration:none!important;}'
            .'@media only screen and (max-width:600px){'
            .'.es-wrap{width:100%!important;}'
            .'.es-col{display:block!important;width:100%!important;padding:0 0 12px 0!important;}'
            .'.es-hide-mobile{display:none!important;}'
            .'}'
            // Outlook.com / some Android clients tag inverted nodes with these.
            .'@media (prefers-color-scheme:dark){'
            .'.es-body{background:#0f172a!important;}'
            .'.es-card{background:#1e293b!important;}'
            .'}'
            .'</style></head>';

        $msoOpen = '<!--[if mso]><table role="presentation" border="0" cellpadding="0" cellspacing="0" width="'
            .$width.'" align="center"><tr><td><![endif]-->';
        $msoClose = '<!--[if mso]></td></tr></table><![endif]-->';

        return $head
            .'<body class="es-body" style="margin:0;padding:0;background:'.$bg.';font-family:'.$s['fontFamily'].';">'
            .$this->preheader($preheader)
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" '
            .'style="background:'.$bg.';"><tr><td align="center" style="padding:24px 12px;">'
            .$msoOpen
            .'<table role="presentation" class="es-wrap es-card" width="'.$width.'" cellpadding="0" cellspacing="0" border="0" '
            .'style="width:'.$width.'px;max-width:100%;background:'.$contentBg.';'
            .'border-radius:'.$radius.'px;overflow:hidden;">'
            .'<tbody>'.$rows.'</tbody></table>'
            .$msoClose
            .'</td></tr></table></body></html>';
    }
}
