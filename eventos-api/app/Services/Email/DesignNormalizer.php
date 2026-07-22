<?php

namespace App\Services\Email;

/**
 * Normalizes the builder's `design` payload before it is persisted.
 *
 * The design is free-form JSON by necessity — the block contract evolves with
 * the editor, and form-request rules can't describe a recursive tree usefully.
 * That makes this the place to enforce the invariants the renderer assumes:
 * known block types, bounded depth and count, scalar style values, and author
 * HTML cleaned at rest as well as at render.
 *
 * Unknown keys on a block are preserved, so a newer editor can round-trip
 * properties this version doesn't understand yet.
 */
class DesignNormalizer
{
    /** Types the renderer knows how to draw; anything else is dropped. */
    public const TYPES = [
        'heading', 'header', 'text', 'button', 'image', 'logo', 'video',
        'divider', 'spacer', 'social', 'columns', 'html', 'footer',
    ];

    private const MAX_DEPTH = 5;

    private const MAX_BLOCKS = 400;

    private const MAX_COLUMNS = 4;

    /** Style values are presentational scalars — never nested structures. */
    private const MAX_STYLE_KEYS = 40;

    private int $count = 0;

    public function __construct(protected HtmlSanitizer $sanitizer) {}

    /**
     * @param  array<string,mixed>  $design  raw { blocks, settings } from the request
     * @return array{blocks:array<int,array<string,mixed>>,settings:array<string,mixed>}
     */
    public function normalize(array $design): array
    {
        $this->count = 0;

        return [
            'blocks' => $this->blocks(is_array($design['blocks'] ?? null) ? $design['blocks'] : [], 0),
            'settings' => $this->settings(is_array($design['settings'] ?? null) ? $design['settings'] : []),
        ];
    }

    /**
     * @param  array<mixed>  $blocks
     * @return array<int,array<string,mixed>>
     */
    private function blocks(array $blocks, int $depth): array
    {
        if ($depth > self::MAX_DEPTH) {
            return [];
        }

        $out = [];

        foreach ($blocks as $block) {
            if ($this->count >= self::MAX_BLOCKS) {
                break;
            }

            if (! is_array($block)) {
                continue;
            }

            $type = is_string($block['type'] ?? null) ? $block['type'] : '';

            if (! in_array($type, self::TYPES, true)) {
                continue;
            }

            $this->count++;
            $out[] = $this->block($block, $type, $depth);
        }

        return $out;
    }

    /**
     * @param  array<string,mixed>  $block
     * @return array<string,mixed>
     */
    private function block(array $block, string $type, int $depth): array
    {
        $clean = $block;
        $clean['type'] = $type;
        $clean['style'] = $this->style(is_array($block['style'] ?? null) ? $block['style'] : []);

        // Rich text and raw-HTML bodies are the only author HTML in the tree.
        if (isset($clean['html']) && is_string($clean['html'])) {
            $clean['html'] = $this->sanitizer->clean($clean['html']);
        }

        if ($type === 'columns') {
            $columns = is_array($block['columns'] ?? null) ? array_values($block['columns']) : [];
            $columns = array_slice($columns, 0, self::MAX_COLUMNS);

            $clean['columns'] = array_map(
                fn ($column) => $this->blocks(is_array($column) ? $column : [], $depth + 1),
                $columns,
            );

            $clean['widths'] = $this->widths($block['widths'] ?? null, count($clean['columns']));
        } else {
            // A non-columns block carrying children is malformed input.
            unset($clean['columns'], $clean['widths']);
        }

        if (isset($clean['items']) && is_array($clean['items'])) {
            $clean['items'] = $this->items($clean['items']);
        }

        return $clean;
    }

    /**
     * Percentages, one per column. Returned null when absent or unusable so the
     * renderer falls back to an even split rather than a broken layout.
     *
     * @return array<int,int>|null
     */
    private function widths(mixed $widths, int $count): ?array
    {
        if ($count === 0 || ! is_array($widths) || count($widths) !== $count) {
            return null;
        }

        $clean = [];

        foreach ($widths as $width) {
            if (! is_numeric($width)) {
                return null;
            }

            $clean[] = max(5, min(95, (int) $width));
        }

        return $clean;
    }

    /**
     * @param  array<mixed>  $items
     * @return array<int,array<string,mixed>>
     */
    private function items(array $items): array
    {
        return array_values(array_map(
            fn ($item) => array_map(
                fn ($value) => is_scalar($value) ? $value : null,
                array_filter((array) $item, 'is_string', ARRAY_FILTER_USE_KEY),
            ),
            array_slice(array_filter($items, 'is_array'), 0, 12),
        ));
    }

    /**
     * @param  array<mixed>  $style
     * @return array<string,mixed>
     */
    private function style(array $style): array
    {
        $out = [];

        foreach ($style as $key => $value) {
            if (! is_string($key) || count($out) >= self::MAX_STYLE_KEYS) {
                continue;
            }

            if (is_bool($value) || is_int($value) || is_float($value)) {
                $out[$key] = $value;
            } elseif (is_string($value)) {
                $out[$key] = mb_substr($value, 0, 200);
            }
        }

        return $out;
    }

    /**
     * @param  array<mixed>  $settings
     * @return array<string,mixed>
     */
    private function settings(array $settings): array
    {
        return $this->style($settings);
    }
}
