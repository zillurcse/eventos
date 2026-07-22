<?php

namespace Tests\Unit;

use App\Services\Email\HtmlSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The sanitizer is the only thing standing between author HTML and both the
 * admin SPA (which renders it) and the recipient's inbox, so the cases here are
 * the evasions that defeat naive filtering — not just the obvious <script>.
 */
class HtmlSanitizerTest extends TestCase
{
    private HtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new HtmlSanitizer;
    }

    public function test_it_keeps_safe_formatting_markup(): void
    {
        $html = '<p style="text-align:center"><strong>Hi</strong> <em>there</em></p>';

        $clean = $this->sanitizer->clean($html);

        $this->assertStringContainsString('<strong>Hi</strong>', $clean);
        $this->assertStringContainsString('<em>there</em>', $clean);
        $this->assertStringContainsString('text-align:center', $clean);
    }

    /** Merge tokens must survive verbatim — the merge pass runs after this. */
    public function test_it_preserves_merge_tokens_in_text_and_urls(): void
    {
        $clean = $this->sanitizer->clean(
            '<p>Hi {{ contact.first_name }}</p><a href="{{ event.url }}">Go</a>'
        );

        $this->assertStringContainsString('{{ contact.first_name }}', $clean);
        $this->assertStringContainsString('href="{{ event.url }}"', $clean);
    }

    #[DataProvider('scriptVectors')]
    public function test_it_strips_script_execution_vectors(string $payload, string $mustNotContain): void
    {
        $clean = $this->sanitizer->clean($payload);

        $this->assertStringNotContainsStringIgnoringCase($mustNotContain, $clean);
    }

    /** @return array<string,array{0:string,1:string}> */
    public static function scriptVectors(): array
    {
        return [
            'script tag' => ['<p>ok</p><script>alert(1)</script>', 'alert'],
            'event handler' => ['<img src="x" onerror="alert(1)">', 'onerror'],
            'uppercase handler' => ['<div OnMouseOver="alert(1)">x</div>', 'onmouseover'],
            'javascript href' => ['<a href="javascript:alert(1)">x</a>', 'javascript:'],
            'mixed-case scheme' => ['<a href="JaVaScRiPt:alert(1)">x</a>', 'javascript:'],
            'whitespace-obfuscated scheme' => ["<a href=\"java\tscript:alert(1)\">x</a>", 'script:'],
            'entity-encoded colon' => ['<a href="javascript&#58;alert(1)">x</a>', 'javascript&#58;'],
            'svg onload' => ['<svg onload="alert(1)"></svg>', 'onload'],
            'iframe' => ['<iframe src="//evil.test"></iframe>', 'iframe'],
            'style expression' => ['<div style="width:expression(alert(1))">x</div>', 'expression'],
            'css import' => ['<div style="background:url(javascript:alert(1))">x</div>', 'javascript'],
            'form' => ['<form action="//evil.test"><input name="pw"></form>', '<form'],
            'comment smuggling' => ['<!--[if IE]><script>alert(1)</script><![endif]-->', 'alert'],
        ];
    }

    /** An unknown wrapper loses the tag but keeps the author's words. */
    public function test_it_unwraps_unknown_elements_but_keeps_their_text(): void
    {
        $clean = $this->sanitizer->clean('<article><p>Kept text</p></article>');

        $this->assertStringNotContainsString('<article', $clean);
        $this->assertStringContainsString('Kept text', $clean);
    }

    /** A blocked element takes its whole subtree with it — no orphaned payload. */
    public function test_it_removes_blocked_elements_with_their_subtree(): void
    {
        $clean = $this->sanitizer->clean('<p>before</p><script><b>payload</b></script><p>after</p>');

        $this->assertStringContainsString('before', $clean);
        $this->assertStringContainsString('after', $clean);
        $this->assertStringNotContainsString('payload', $clean);
    }

    public function test_it_allows_raster_data_uris_but_not_svg(): void
    {
        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUg==';

        $this->assertStringContainsString($png, $this->sanitizer->clean('<img src="'.$png.'">'));
        $this->assertStringNotContainsString(
            'svg+xml',
            $this->sanitizer->clean('<img src="data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=">')
        );
    }

    public function test_it_hardens_surviving_links(): void
    {
        $clean = $this->sanitizer->clean('<a href="https://example.test">Go</a>');

        $this->assertStringContainsString('target="_blank"', $clean);
        $this->assertStringContainsString('rel="noopener noreferrer"', $clean);
    }

    public function test_it_gives_images_an_alt_attribute(): void
    {
        $this->assertStringContainsString('alt=""', $this->sanitizer->clean('<img src="https://a.test/x.png">'));
    }

    public function test_it_keeps_relative_and_anchor_urls(): void
    {
        $clean = $this->sanitizer->clean('<a href="/events">a</a><a href="#top">b</a>');

        $this->assertStringContainsString('href="/events"', $clean);
        $this->assertStringContainsString('href="#top"', $clean);
    }

    public function test_it_drops_unsupported_css_properties(): void
    {
        $clean = $this->sanitizer->clean('<div style="color:red;position:fixed;top:0">x</div>');

        $this->assertStringContainsString('color:red', $clean);
        $this->assertStringNotContainsString('position', $clean);
    }

    public function test_it_handles_empty_and_plain_input(): void
    {
        $this->assertSame('', $this->sanitizer->clean(null));
        $this->assertSame('', $this->sanitizer->clean('   '));
        $this->assertStringContainsString('just words', $this->sanitizer->clean('just words'));
    }

    /** UTF-8 must round-trip — libxml mangles it without an encoding hint. */
    public function test_it_preserves_non_ascii_text(): void
    {
        $clean = $this->sanitizer->clean('<p>Rendez-vous à Montréal — 日本語</p>');

        $this->assertStringContainsString('à Montréal', $clean);
        $this->assertStringContainsString('日本語', $clean);
    }
}
