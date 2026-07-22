<?php

namespace App\Services\Email;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allow-list sanitizer for the two places the builder accepts author HTML: the
 * rich text of a `text` block and the body of a raw `html` block.
 *
 * Both are attacker-reachable in practice — an organizer with `email.manage` is
 * not necessarily trusted against their own tenant's other admins, the preview
 * endpoint renders *unsaved* input, and the resulting markup is displayed inside
 * the admin SPA as well as mailed out. So this runs at render time, which is the
 * single choke point every path (preview, preview-draft, compile, send) passes
 * through — a block that never went through `store` still gets cleaned.
 *
 * Deliberately DOM-based rather than regex: regex cannot see through the
 * encoding tricks (`<img src=x onerror=…>`, `<svg/onload=…>`, mixed-case
 * `JaVaScRiPt:`, entity-escaped colons) that defeat naive stripping.
 *
 * Merge tokens (`{{ contact.first_name }}`) survive untouched — they are plain
 * text to the parser, and are resolved after this pass by EmailRenderer::merge().
 */
class HtmlSanitizer
{
    /**
     * Elements that may appear in the output. Anything email clients strip
     * anyway (script/style/iframe/form) is pointless *and* dangerous, so the
     * allow-list doubles as a "what actually renders in Outlook" filter.
     */
    private const ALLOWED_TAGS = [
        'a', 'b', 'blockquote', 'br', 'center', 'code', 'div', 'em', 'font', 'h1', 'h2', 'h3',
        'h4', 'h5', 'h6', 'hr', 'i', 'img', 'li', 'ol', 'p', 'pre', 's', 'small', 'span',
        'strike', 'strong', 'sub', 'sup', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead',
        'tr', 'u', 'ul',
    ];

    /**
     * Removed *with their subtree*. Everything else that is merely not allowed
     * gets unwrapped (children kept), because an unknown wrapper is usually a
     * paste artefact whose text the author still wants.
     */
    private const VOID_SUBTREE_TAGS = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet',
        'form', 'input', 'button', 'select', 'option', 'textarea', 'link', 'meta',
        'base', 'svg', 'math', 'template', 'noscript', 'audio', 'video', 'canvas',
    ];

    /** Attributes allowed on any element. */
    private const GLOBAL_ATTRS = ['style', 'class', 'title', 'dir', 'lang', 'align', 'valign'];

    /** Extra attributes allowed per element. */
    private const TAG_ATTRS = [
        'a' => ['href', 'target', 'rel', 'name'],
        'img' => ['src', 'alt', 'width', 'height', 'border'],
        'table' => ['width', 'height', 'border', 'cellpadding', 'cellspacing', 'bgcolor', 'role'],
        'td' => ['width', 'height', 'colspan', 'rowspan', 'bgcolor', 'nowrap'],
        'th' => ['width', 'height', 'colspan', 'rowspan', 'bgcolor', 'nowrap'],
        'tr' => ['height', 'bgcolor'],
        'ol' => ['start', 'type'],
        'font' => ['color', 'face', 'size'],
        'hr' => ['width', 'size', 'noshade'],
    ];

    /**
     * CSS properties allowed in a `style` attribute. Layout/positioning
     * properties are excluded: they are unsupported in most mail clients and are
     * the ones used to overlay or hide content in the admin preview.
     */
    private const ALLOWED_CSS = [
        'background', 'background-color', 'background-image', 'border', 'border-bottom',
        'border-bottom-color', 'border-bottom-style', 'border-bottom-width', 'border-collapse',
        'border-color', 'border-left', 'border-radius', 'border-right', 'border-spacing',
        'border-style', 'border-top', 'border-width', 'color', 'display', 'font', 'font-family',
        'font-size', 'font-style', 'font-variant', 'font-weight', 'height', 'letter-spacing',
        'line-height', 'list-style', 'list-style-type', 'margin', 'margin-bottom', 'margin-left',
        'margin-right', 'margin-top', 'max-height', 'max-width', 'min-height', 'min-width',
        'padding', 'padding-bottom', 'padding-left', 'padding-right', 'padding-top',
        'text-align', 'text-decoration', 'text-transform', 'vertical-align', 'white-space',
        'width', 'word-break', 'word-wrap',
    ];

    /** URL schemes permitted in href/src. */
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel', 'cid'];

    /** Hard ceiling so a pathological paste can't blow up the parser or the row. */
    private const MAX_LENGTH = 200_000;

    public function clean(?string $html): string
    {
        $html = (string) $html;

        if (trim($html) === '') {
            return '';
        }

        if (mb_strlen($html) > self::MAX_LENGTH) {
            $html = mb_substr($html, 0, self::MAX_LENGTH);
        }

        $doc = new DOMDocument('1.0', 'UTF-8');

        // libxml mangles UTF-8 without an explicit encoding hint, and shouts about
        // HTML5 tags it doesn't know. The wrapper div gives us a stable root to
        // serialize the fragment back out of.
        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadHTML(
            '<?xml encoding="UTF-8"?><div id="es-root">'.$html.'</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            // Unparseable markup is not worth guessing at — fall back to text.
            return e(strip_tags($html));
        }

        $root = $doc->getElementById('es-root');

        if (! $root instanceof DOMElement) {
            return '';
        }

        $this->cleanChildren($root);

        return $this->innerHtml($doc, $root);
    }

    /** Walk a snapshot of the child list — the live NodeList shifts as we edit. */
    private function cleanChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $child) {
            $this->cleanNode($child);
        }
    }

    private function cleanNode(DOMNode $node): void
    {
        // Comments can smuggle conditional-comment markup past naive filters.
        if ($node->nodeType === XML_COMMENT_NODE) {
            $node->parentNode?->removeChild($node);

            return;
        }

        if ($node->nodeType === XML_TEXT_NODE) {
            return;
        }

        if (! $node instanceof DOMElement) {
            $node->parentNode?->removeChild($node);

            return;
        }

        $tag = strtolower($node->nodeName);

        if (in_array($tag, self::VOID_SUBTREE_TAGS, true)) {
            $node->parentNode?->removeChild($node);

            return;
        }

        if (! in_array($tag, self::ALLOWED_TAGS, true)) {
            $this->unwrap($node);

            return;
        }

        $this->cleanAttributes($node, $tag);
        $this->cleanChildren($node);
    }

    /** Replace an element with its children, keeping the author's text. */
    private function unwrap(DOMElement $node): void
    {
        $parent = $node->parentNode;

        if (! $parent) {
            return;
        }

        foreach (iterator_to_array($node->childNodes) as $child) {
            $this->cleanNode($child);

            // cleanNode may have detached it (comment, blocked subtree).
            if ($child->parentNode === $node) {
                $parent->insertBefore($child, $node);
            }
        }

        $parent->removeChild($node);
    }

    private function cleanAttributes(DOMElement $node, string $tag): void
    {
        $allowed = array_merge(self::GLOBAL_ATTRS, self::TAG_ATTRS[$tag] ?? []);

        foreach (iterator_to_array($node->attributes) as $attr) {
            /** @var DOMAttr $attr */
            $name = strtolower($attr->nodeName);

            // `on*` is covered by the allow-list, but drop it explicitly so the
            // intent survives any future widening of that list.
            if (str_starts_with($name, 'on') || ! in_array($name, $allowed, true)) {
                $node->removeAttribute($attr->nodeName);

                continue;
            }

            $value = match ($name) {
                'style' => $this->cleanStyle($attr->nodeValue ?? ''),
                'href', 'src' => $this->cleanUrl($attr->nodeValue ?? ''),
                default => $attr->nodeValue ?? '',
            };

            if (trim($value) === '') {
                $node->removeAttribute($attr->nodeName);

                continue;
            }

            $node->setAttribute($attr->nodeName, $value);
        }

        // Any surviving link opens in a new tab and must not hand the opener
        // window to the destination.
        if ($tag === 'a' && $node->hasAttribute('href')) {
            $node->setAttribute('target', '_blank');
            $node->setAttribute('rel', 'noopener noreferrer');
        }

        // An image with no alt text is an accessibility hole and shows nothing
        // in the many clients that block images by default.
        if ($tag === 'img' && ! $node->hasAttribute('alt')) {
            $node->setAttribute('alt', '');
        }
    }

    /** Keep only allow-listed declarations with values free of code-execution syntax. */
    private function cleanStyle(string $style): string
    {
        $out = [];

        foreach (explode(';', $style) as $declaration) {
            if (! str_contains($declaration, ':')) {
                continue;
            }

            [$property, $value] = explode(':', $declaration, 2);
            $property = strtolower(trim($property));
            $value = trim($value);

            if ($property === '' || $value === '' || ! in_array($property, self::ALLOWED_CSS, true)) {
                continue;
            }

            // expression()/behavior/binding are legacy script vectors; url() is
            // only safe once the inner URL passes the same scheme check as href.
            $lower = strtolower($value);

            if (preg_match('/(expression|behaviou?r|-moz-binding|@import|javascript:|vbscript:)/i', $lower)) {
                continue;
            }

            if (str_contains($lower, 'url(')) {
                if (! preg_match('/url\(\s*[\'"]?([^\'")]+)/i', $value, $m) || $this->cleanUrl($m[1]) === '') {
                    continue;
                }
            }

            $out[] = "{$property}:{$value}";
        }

        return implode(';', $out);
    }

    /**
     * Normalize a URL, returning '' for anything not provably safe.
     *
     * Merge tokens pass through untouched — the value is not a URL yet, and the
     * merge pass escapes whatever replaces the token.
     */
    private function cleanUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        if (str_contains($url, '{{')) {
            return $url;
        }

        // Strip characters that let a scheme hide from the check below
        // ("java\0script:", "java\tscript:", entity-encoded colons).
        $probe = strtolower(preg_replace('/[\x00-\x20]|&#[xX]?0*[0-9a-fA-F]+;?/', '', $url) ?? '');

        // Relative, root-relative and in-document links carry no scheme.
        if (str_starts_with($probe, '#') || str_starts_with($probe, '/') || ! str_contains($probe, ':')) {
            return $url;
        }

        // data: is allowed only for raster images — data:image/svg+xml is a
        // script-execution vector, and clients strip the rest regardless.
        if (str_starts_with($probe, 'data:')) {
            return preg_match('#^data:image/(png|jpe?g|gif|webp);base64,[a-z0-9+/=\s]+$#i', $url) ? $url : '';
        }

        $scheme = strtolower(strtok($probe, ':') ?: '');

        return in_array($scheme, self::ALLOWED_SCHEMES, true) ? $url : '';
    }

    /** Serialize a node's children (we never want the wrapper itself). */
    private function innerHtml(DOMDocument $doc, DOMElement $root): string
    {
        $html = '';

        foreach ($root->childNodes as $child) {
            $html .= $doc->saveHTML($child);
        }

        return trim($this->restoreMergeTokens($html));
    }

    /**
     * libxml percent-encodes braces when serializing URI attributes, so a
     * `href="{{ event.url }}"` comes back out as `href="%7B%7B%20event.url…"`
     * and the merge pass no longer recognizes it. Put those tokens back.
     *
     * Only `%20` and word/dot characters are decoded between the braces, so this
     * cannot resurrect a scheme (`:`) or any other structure the URL check
     * already rejected.
     */
    private function restoreMergeTokens(string $html): string
    {
        return preg_replace_callback(
            '/%7B%7B((?:%20|[\w.])*)%7D%7D/i',
            fn (array $m) => '{{'.str_replace('%20', ' ', $m[1]).'}}',
            $html,
        ) ?? $html;
    }
}
