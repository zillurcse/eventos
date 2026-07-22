<?php

namespace Tests\Unit;

use App\Services\Email\EmailRenderer;
use App\Services\Email\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

/**
 * Covers the guarantees the renderer makes to the send pipeline: that author
 * input can't inject markup, that hostile or malformed designs degrade instead
 * of breaking the layout, and that the client-compatibility scaffolding
 * (ghost tables, preheader, stacking hooks) is actually emitted.
 */
class EmailRendererTest extends TestCase
{
    private EmailRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new EmailRenderer(new HtmlSanitizer);
    }

    /** @param array<string,mixed> $extra */
    private function block(string $type, array $extra = []): array
    {
        return array_merge(['type' => $type, 'style' => []], $extra);
    }

    // ── document scaffolding ────────────────────────────────────────────────

    public function test_it_emits_an_outlook_ghost_table_at_the_container_width(): void
    {
        $html = $this->renderer->render([$this->block('text', ['html' => 'hi'])], ['contentWidth' => 640]);

        $this->assertStringContainsString('<!--[if mso]>', $html);
        $this->assertStringContainsString('width="640"', $html);
    }

    public function test_it_renders_the_preheader_hidden_before_the_body(): void
    {
        $html = $this->renderer->render([$this->block('text', ['html' => 'VISIBLE-COPY'])], [], 'Your seat is confirmed');

        $this->assertStringContainsString('Your seat is confirmed', $html);
        $this->assertStringContainsString('display:none', $html);
        // It must precede the visible content to win the inbox preview.
        $this->assertLessThan(strpos($html, 'VISIBLE-COPY'), strpos($html, 'Your seat is confirmed'));
    }

    public function test_it_omits_the_preheader_block_when_unset(): void
    {
        $html = $this->renderer->render([$this->block('text', ['html' => 'body'])]);

        $this->assertStringNotContainsString('mso-hide:all', $html);
    }

    public function test_it_declares_dark_mode_support_and_mobile_stacking(): void
    {
        $html = $this->renderer->render([$this->block('text', ['html' => 'hi'])]);

        $this->assertStringContainsString('name="color-scheme"', $html);
        $this->assertStringContainsString('.es-col{display:block!important', $html);
    }

    public function test_content_width_is_clamped_to_a_sane_range(): void
    {
        $html = $this->renderer->render([$this->block('text', ['html' => 'x'])], ['contentWidth' => 99999]);

        $this->assertStringContainsString('width:900px', $html);
        $this->assertStringNotContainsString('99999', $html);
    }

    // ── escaping ────────────────────────────────────────────────────────────

    public function test_heading_text_is_escaped(): void
    {
        $html = $this->renderer->render([$this->block('heading', ['text' => '<script>alert(1)</script>'])]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_rich_text_html_is_sanitized_not_trusted(): void
    {
        $html = $this->renderer->render([
            $this->block('text', ['html' => '<b>keep</b><img src=x onerror="alert(1)">']),
        ]);

        $this->assertStringContainsString('<b>keep</b>', $html);
        $this->assertStringNotContainsString('onerror', $html);
    }

    /** The raw-HTML block is the most dangerous input in the builder. */
    public function test_raw_html_block_is_sanitized(): void
    {
        $html = $this->renderer->render([
            $this->block('html', ['html' => '<table><tr><td>ok</td></tr></table><script>alert(1)</script>']),
        ]);

        $this->assertStringContainsString('ok', $html);
        $this->assertStringNotContainsString('alert(1)', $html);
    }

    public function test_button_label_and_alt_text_are_escaped(): void
    {
        $html = $this->renderer->render([
            $this->block('button', ['text' => '"><script>x</script>', 'url' => 'https://a.test']),
            $this->block('image', ['src' => 'https://a.test/i.png', 'alt' => '"onload="alert(1)']),
        ]);

        $this->assertStringNotContainsString('<script>', $html);
        // The alt text still *reads* `onload=`, but its quotes are entity-encoded,
        // so it cannot break out of the attribute into a real handler.
        $this->assertStringContainsString('alt="&quot;onload=&quot;alert(1)"', $html);
        $this->assertStringNotContainsString('" onload="', $html);
    }

    // ── URLs ────────────────────────────────────────────────────────────────

    public function test_dangerous_button_urls_become_inert(): void
    {
        $html = $this->renderer->render([
            $this->block('button', ['text' => 'Go', 'url' => 'javascript:alert(1)']),
        ]);

        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('href="#"', $html);
    }

    public function test_schemeless_urls_are_upgraded_to_https(): void
    {
        $html = $this->renderer->render([
            $this->block('button', ['text' => 'Go', 'url' => 'example.test/tickets']),
        ]);

        $this->assertStringContainsString('https://example.test/tickets', $html);
    }

    public function test_merge_tokens_in_urls_survive_rendering(): void
    {
        $html = $this->renderer->render([
            $this->block('button', ['text' => 'Go', 'url' => '{{ event.url }}']),
        ]);

        $this->assertStringContainsString('{{ event.url }}', $html);
    }

    // ── merge pass ──────────────────────────────────────────────────────────

    public function test_merge_resolves_dotted_tokens_and_escapes_values(): void
    {
        $merged = $this->renderer->merge(
            '<p>Hi {{ contact.first_name }}</p>',
            ['contact' => ['first_name' => '<script>x</script>']],
        );

        $this->assertStringNotContainsString('<script>', $merged);
        $this->assertStringContainsString('&lt;script&gt;', $merged);
    }

    public function test_merge_blanks_unknown_tokens(): void
    {
        $this->assertSame('<p>Hi </p>', $this->renderer->merge('<p>Hi {{ nope.missing }}</p>', []));
    }

    // ── columns ─────────────────────────────────────────────────────────────

    public function test_columns_honour_explicit_widths(): void
    {
        $html = $this->renderer->render([
            $this->block('columns', [
                'widths' => [70, 30],
                'columns' => [
                    [$this->block('text', ['html' => 'left'])],
                    [$this->block('text', ['html' => 'right'])],
                ],
            ]),
        ]);

        $this->assertStringContainsString('width="70%"', $html);
        $this->assertStringContainsString('width="30%"', $html);
    }

    public function test_column_widths_are_rescaled_to_one_hundred_percent(): void
    {
        $html = $this->renderer->render([
            $this->block('columns', [
                'widths' => [10, 10],
                'columns' => [
                    [$this->block('text', ['html' => 'a'])],
                    [$this->block('text', ['html' => 'b'])],
                ],
            ]),
        ]);

        $this->assertStringContainsString('width="50%"', $html);
    }

    public function test_malformed_column_widths_fall_back_to_an_even_split(): void
    {
        $html = $this->renderer->render([
            $this->block('columns', [
                'widths' => ['nonsense'],
                'columns' => [
                    [$this->block('text', ['html' => 'a'])],
                    [$this->block('text', ['html' => 'b'])],
                ],
            ]),
        ]);

        $this->assertStringContainsString('width="50%"', $html);
    }

    /** A hand-crafted payload must not recurse the renderer to death. */
    public function test_deeply_nested_columns_stop_at_the_depth_limit(): void
    {
        $block = $this->block('text', ['html' => 'deep']);

        for ($i = 0; $i < 40; $i++) {
            $block = $this->block('columns', ['columns' => [[$block]]]);
        }

        $html = $this->renderer->render([$block]);

        $this->assertStringNotContainsString('deep', $html);
        $this->assertStringContainsString('</html>', $html);
    }

    // ── robustness ──────────────────────────────────────────────────────────

    public function test_unknown_and_malformed_blocks_are_skipped(): void
    {
        $html = $this->renderer->render([
            ['type' => 'not-a-real-block'],
            ['no_type' => true],
            'a bare string',
            $this->block('text', ['html' => 'survivor']),
        ]);

        $this->assertStringContainsString('survivor', $html);
        $this->assertStringContainsString('</html>', $html);
    }

    public function test_empty_blocks_render_no_row(): void
    {
        $html = $this->renderer->render([
            $this->block('heading', ['text' => '   ']),
            $this->block('image', ['src' => '']),
        ]);

        // The outer scaffolding always has rows; the content table must be empty.
        $this->assertStringContainsString('<tbody></tbody>', $html);
    }

    public function test_hide_on_mobile_tags_the_row_for_the_media_query(): void
    {
        $html = $this->renderer->render([
            $this->block('text', ['html' => 'desktop only', 'style' => ['hideOnMobile' => true]]),
        ]);

        $this->assertStringContainsString('class="es-hide-mobile"', $html);
    }

    public function test_alignment_only_accepts_real_keywords(): void
    {
        $html = $this->renderer->render([
            $this->block('heading', ['text' => 'Hi', 'style' => ['align' => 'center; background:url(x)']]),
        ]);

        $this->assertStringContainsString('text-align:left', $html);
        $this->assertStringNotContainsString('url(x)', $html);
    }

    public function test_social_falls_back_to_branded_chips_without_hosted_icons(): void
    {
        $html = $this->renderer->render([
            $this->block('social', ['items' => [['network' => 'linkedin', 'url' => 'https://li.test/x']]]),
        ]);

        $this->assertStringContainsString('https://li.test/x', $html);
        $this->assertStringContainsString('#0a66c2', $html);
    }

    public function test_social_uses_hosted_icons_when_a_base_url_is_configured(): void
    {
        $html = $this->renderer->render(
            [$this->block('social', ['items' => [['network' => 'facebook', 'url' => 'https://fb.test/x']]])],
            ['socialIconBaseUrl' => 'https://cdn.test/icons'],
        );

        $this->assertStringContainsString('https://cdn.test/icons/facebook.png', $html);
    }

    public function test_it_renders_an_empty_design_without_error(): void
    {
        $html = $this->renderer->render([]);

        $this->assertStringContainsString('<!doctype html>', $html);
        $this->assertStringContainsString('</html>', $html);
    }
}
