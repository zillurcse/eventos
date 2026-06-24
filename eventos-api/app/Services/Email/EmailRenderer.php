<?php

namespace App\Services\Email;

/**
 * Renders builder blocks into email-client-safe HTML (table layout + inline
 * styles) and merges {{ handlebars }} variables (architecture §6.13).
 *
 * NOTE: a pure-PHP renderer — no Node/MJML dependency. The block→HTML contract
 * is identical, so an MjmlRenderer can be swapped in later without touching
 * callers.
 */
class EmailRenderer
{
    /** @param array<int,array{type?:string,content?:array}> $blocks */
    public function render(array $blocks): string
    {
        $rows = '';
        foreach ($blocks as $block) {
            $rows .= $this->block($block['type'] ?? 'text', $block['content'] ?? []);
        }

        return $this->wrap($rows);
    }

    public function merge(string $html, array $vars): string
    {
        return preg_replace_callback(
            '/\{\{\s*([\w.]+)\s*\}\}/',
            fn ($m) => e((string) data_get($vars, $m[1], '')),
            $html,
        );
    }

    protected function block(string $type, array $c): string
    {
        $cell = fn (string $inner, string $style = '') => "<tr><td style=\"padding:16px 24px;{$style}\">{$inner}</td></tr>";

        return match ($type) {
            'header' => $cell('<h1 style="margin:0;font:600 24px system-ui,sans-serif;color:#0f172a;">'.e($c['text'] ?? '').'</h1>', 'background:#f8fafc;'),
            'text' => $cell('<div style="font:400 15px/1.6 system-ui,sans-serif;color:#334155;">'.($c['html'] ?? nl2br(e($c['text'] ?? ''))).'</div>'),
            'image' => $cell('<img src="'.e($c['src'] ?? '').'" alt="'.e($c['alt'] ?? '').'" style="max-width:100%;display:block;border-radius:8px;">'),
            'button' => $cell('<a href="'.e($c['url'] ?? '#').'" style="display:inline-block;padding:12px 22px;background:#4338ca;color:#fff;font:600 14px system-ui,sans-serif;text-decoration:none;border-radius:8px;">'.e($c['text'] ?? 'Open').'</a>'),
            'divider' => '<tr><td style="padding:0 24px;"><hr style="border:none;border-top:1px solid #e2e8f0;margin:8px 0;"></td></tr>',
            'footer' => $cell('<div style="font:400 12px system-ui,sans-serif;color:#94a3b8;">'.e($c['text'] ?? '').'</div>', 'background:#f8fafc;'),
            default => $cell('<pre style="font:12px monospace;color:#64748b;">'.e(json_encode($c)).'</pre>'),
        };
    }

    protected function wrap(string $rows): string
    {
        return '<!doctype html><html><head><meta charset="utf-8"></head>'
            .'<body style="margin:0;background:#f1f5f9;">'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 0;"><tr><td align="center">'
            .'<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;max-width:600px;">'
            .'<tbody>'.$rows.'</tbody></table>'
            .'</td></tr></table></body></html>';
    }
}
