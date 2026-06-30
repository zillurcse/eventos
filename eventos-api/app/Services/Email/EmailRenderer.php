<?php

namespace App\Services\Email;

use Illuminate\Support\Str;

/**
 * Renders builder blocks into email-client-safe HTML (table layout + inline
 * styles, responsive column stacking) and merges {{ handlebars }} variables
 * (architecture §6.13).
 *
 * The block contract is flat: each block is { type, ...props, style:{...} }.
 * `columns` blocks nest child blocks per column. A pure-PHP renderer with no
 * Node/MJML dependency — an MjmlRenderer could replace it without touching
 * callers.
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

    /**
     * @param  array<int,array<string,mixed>>  $blocks
     * @param  array<string,mixed>  $settings  global canvas settings
     */
    public function render(array $blocks, array $settings = []): string
    {
        $s = array_merge($this->defaults, array_filter($settings, fn ($v) => $v !== null && $v !== ''));

        $rows = '';
        foreach ($blocks as $block) {
            $rows .= $this->renderBlock($block, $s);
        }

        return $this->wrap($rows, $s);
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
    protected function renderBlock(array $block, array $s): string
    {
        // legacy blocks nested props under `content`; flatten for back-compat.
        $p = isset($block['content']) && is_array($block['content'])
            ? array_merge($block['content'], $block)
            : $block;

        $type = $block['type'] ?? 'text';
        $style = $block['style'] ?? [];

        $cellStyle = $this->padding($style)
            .$this->bg($style['backgroundColor'] ?? null);

        $inner = $this->inner($type, $p, $style, $s);

        if ($inner === '') {
            return '';
        }

        return "<tr><td style=\"{$cellStyle}\">{$inner}</td></tr>";
    }

    /** The inner HTML of a block (no outer row/padding) — reused inside columns. */
    protected function inner(string $type, array $p, array $style, array $s): string
    {
        return match ($type) {
            'heading', 'header' => $this->heading($p, $style, $s),
            'text' => $this->text($p, $style, $s),
            'button' => $this->button($p, $style, $s),
            'image' => $this->image($p, $style),
            'divider' => $this->divider($style),
            'spacer' => $this->spacer($style),
            'social' => $this->social($p, $style),
            'columns' => $this->columns($p, $style, $s),
            'html' => (string) ($p['html'] ?? ''),
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
        // `html` is trusted rich text from the editor; `text` is the plain fallback.
        $body = $p['html'] ?? null;
        $body = $body !== null && $body !== '' ? $body : nl2br(e((string) ($p['text'] ?? '')));
        if (trim(strip_tags((string) $body)) === '' && ! str_contains((string) $body, '<img')) {
            return '';
        }
        $css = $this->typo($style, $s, (int) ($style['fontSize'] ?? 15), $style['fontWeight'] ?? '400', $style['color'] ?? $s['textColor'])
            .'line-height:'.($style['lineHeight'] ?? '1.6').';';

        return "<div style=\"{$css}\">{$body}</div>";
    }

    protected function button(array $p, array $style, array $s): string
    {
        $text = trim((string) ($p['text'] ?? ''));
        if ($text === '') {
            return '';
        }
        $align = $style['align'] ?? 'left';
        $bg = $style['backgroundColor'] ?? $s['linkColor'];
        $color = $style['color'] ?? '#ffffff';
        $radius = (int) ($style['borderRadius'] ?? 8);
        $px = (int) ($style['paddingX'] ?? 24);
        $py = (int) ($style['paddingY'] ?? 13);
        $size = (int) ($style['fontSize'] ?? 15);
        $width = ($style['fullWidth'] ?? false) ? 'display:block;text-align:center;' : 'display:inline-block;';
        $url = $this->url($p['url'] ?? '#');

        $a = "<a href=\"{$url}\" target=\"_blank\" style=\"{$width}padding:{$py}px {$px}px;"
            .'background:'.e($bg).';color:'.e($color).";font-family:{$s['fontFamily']};font-size:{$size}px;"
            .'font-weight:600;text-decoration:none;border-radius:'."{$radius}px;\">".e($text).'</a>';

        return "<div style=\"text-align:".e($align).";\">{$a}</div>";
    }

    protected function image(array $p, array $style): string
    {
        $src = trim((string) ($p['src'] ?? ''));
        if ($src === '') {
            return '';
        }
        $align = $style['align'] ?? 'center';
        $width = isset($style['width']) && $style['width'] !== '' ? (int) $style['width'].'%' : '100%';
        $radius = (int) ($style['borderRadius'] ?? 0);
        $img = '<img src="'.$this->url($src).'" alt="'.e($p['alt'] ?? '').'" '
            .'style="width:'.$width.';max-width:100%;height:auto;display:inline-block;border:0;border-radius:'.$radius.'px;">';

        if (! empty($p['href'])) {
            $img = '<a href="'.$this->url($p['href']).'" target="_blank">'.$img.'</a>';
        }

        return '<div style="text-align:'.e($align).';">'.$img.'</div>';
    }

    protected function divider(array $style): string
    {
        $color = $style['color'] ?? '#e2e8f0';
        $thickness = (int) ($style['height'] ?? 1);
        $width = isset($style['width']) && $style['width'] !== '' ? (int) $style['width'].'%' : '100%';

        return '<div style="font-size:0;line-height:0;"><hr style="border:0;border-top:'
            .$thickness.'px solid '.e($color).';width:'.$width.';margin:0 auto;"></div>';
    }

    protected function spacer(array $style): string
    {
        $h = (int) ($style['height'] ?? 24);

        return '<div style="height:'.$h.'px;line-height:'.$h.'px;font-size:1px;">&nbsp;</div>';
    }

    protected function social(array $p, array $style): string
    {
        $items = array_filter($p['items'] ?? [], fn ($i) => ! empty($i['url']));
        if (empty($items)) {
            return '';
        }
        $align = $style['align'] ?? 'center';
        $size = (int) ($style['iconSize'] ?? 28);
        $color = $style['color'] ?? '#64748b';
        $links = '';
        foreach ($items as $item) {
            $label = ucfirst((string) ($item['network'] ?? 'link'));
            $links .= '<a href="'.$this->url($item['url']).'" target="_blank" '
                .'style="display:inline-block;margin:0 6px;font-family:Arial,sans-serif;font-size:'
                .max(11, (int) ($size / 2)).'px;color:'.e($color).';text-decoration:none;">'.e($label).'</a>';
        }

        return '<div style="text-align:'.e($align).';">'.$links.'</div>';
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

    /** Multi-column layout — each column a <td> rendering its own child blocks. */
    protected function columns(array $p, array $style, array $s): string
    {
        $cols = $p['columns'] ?? [];
        $count = max(1, count($cols));
        if (empty($cols)) {
            return '';
        }
        $gap = (int) ($style['gap'] ?? 16);
        $cells = '';
        foreach ($cols as $i => $children) {
            $children = is_array($children) ? $children : [];
            $body = '';
            foreach ($children as $child) {
                $cp = isset($child['content']) && is_array($child['content'])
                    ? array_merge($child['content'], $child) : $child;
                $cStyle = $child['style'] ?? [];
                $childInner = $this->inner($child['type'] ?? 'text', $cp, $cStyle, $s);
                if ($childInner !== '') {
                    $body .= '<div style="'.$this->padding($cStyle, 6).'">'.$childInner.'</div>';
                }
            }
            $half = (int) ($gap / 2);
            $pad = $i === 0 ? "padding-right:{$half}px;" : ($i === $count - 1 ? "padding-left:{$half}px;" : "padding:0 {$half}px;");
            $cells .= '<td class="es-col" valign="top" width="'.floor(100 / $count).'%" style="'.$pad.'">'.($body ?: '&nbsp;').'</td>';
        }

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'.$cells.'</tr></table>';
    }

    // ── style helpers ───────────────────────────────────────────────────────

    protected function typo(array $style, array $s, int $size, string $weight, string $color): string
    {
        $align = $style['align'] ?? 'left';

        return "font-family:{$s['fontFamily']};font-size:{$size}px;font-weight:".e($weight).';'
            .'color:'.e($color).';text-align:'.e($align).';';
    }

    protected function padding(array $style, int $fallback = 16): string
    {
        $top = $style['paddingTop'] ?? $fallback;
        $bottom = $style['paddingBottom'] ?? $fallback;
        $left = $style['paddingLeft'] ?? 24;
        $right = $style['paddingRight'] ?? 24;

        return "padding:{$top}px {$right}px {$bottom}px {$left}px;";
    }

    protected function bg(?string $color): string
    {
        return $color ? 'background-color:'.e($color).';' : '';
    }

    /** Normalize/guard a URL: keep merge tokens intact, default unsafe to #. */
    protected function url(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '#';
        }
        if (Str::contains($url, '{{')) {
            return e($url); // a merge token — leave it for the merge pass
        }
        if (Str::startsWith($url, ['http://', 'https://', 'mailto:', 'tel:', '#', '/'])) {
            return e($url);
        }

        return e('https://'.$url);
    }

    protected function wrap(string $rows, array $s): string
    {
        $width = (int) $s['contentWidth'];
        $radius = (int) $s['borderRadius'];

        $head = '<!doctype html><html xmlns="http://www.w3.org/1999/xhtml"><head>'
            .'<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<meta http-equiv="X-UA-Compatible" content="IE=edge">'
            .'<style>'
            .'body{margin:0;padding:0;width:100%!important;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;}'
            .'img{border:0;outline:none;text-decoration:none;}'
            .'a{color:'.e($s['linkColor']).';}'
            .'@media only screen and (max-width:600px){'
            .'.es-wrap{width:100%!important;}'
            .'.es-col{display:block!important;width:100%!important;padding:0 0 12px 0!important;}'
            .'}</style></head>';

        return $head
            .'<body style="margin:0;background:'.e($s['backgroundColor']).';font-family:'.$s['fontFamily'].';">'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" '
            .'style="background:'.e($s['backgroundColor']).';padding:24px 12px;"><tr><td align="center">'
            .'<table role="presentation" class="es-wrap" width="'.$width.'" cellpadding="0" cellspacing="0" border="0" '
            .'style="width:'.$width.'px;max-width:100%;background:'.e($s['contentBackground']).';'
            .'border-radius:'.$radius.'px;overflow:hidden;">'
            .'<tbody>'.$rows.'</tbody></table>'
            .'</td></tr></table></body></html>';
    }
}
