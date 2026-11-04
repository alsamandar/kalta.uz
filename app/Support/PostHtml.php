<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * The only HTML a post may contain. Whatever the browser sends (the editor's markup, something pasted from Word or a
 * crafted request) is rebuilt from scratch with a short list of elements and attributes, so nothing can run on the
 * page: no scripts, styles, event handlers, iframes or javascript: links. Pictures may only come from our own uploads.
 */
class PostHtml
{
    /** Allowed element => allowed attributes. Headings start at h3: the post title is the page's h1. */
    private const ALLOWED = [
        'p' => [], 'br' => [], 'h3' => [], 'h4' => [], 'blockquote' => [], 'hr' => [],
        'strong' => [], 'em' => [], 'u' => [], 's' => [], 'code' => [], 'pre' => [],
        'ul' => [], 'ol' => [], 'li' => [],
        'a' => ['href'],
        'figure' => [], 'img' => ['src', 'alt'], 'figcaption' => [],
    ];

    /** Same meaning, other spelling: kept, under the name above. */
    private const RENAME = ['b' => 'strong', 'i' => 'em', 'strike' => 's', 'del' => 's', 'h1' => 'h3', 'h2' => 'h3', 'h5' => 'h4', 'h6' => 'h4', 'div' => 'p'];

    /** Dropped together with everything inside them. */
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'noscript', 'template', 'svg', 'math', 'form', 'input', 'button', 'select', 'textarea', 'head', 'title', 'meta', 'link'];

    /** Elements that hold paragraphs; inline content directly inside them is wrapped in a <p>. */
    private const BLOCKS = ['p', 'h3', 'h4', 'blockquote', 'hr', 'ul', 'ol', 'pre', 'figure'];

    /** Our own uploads only (see Images::post and MediaController). */
    private const IMAGE_SRC = '#^/?media/posts/[A-Za-z0-9_-]+\.(?:jpe?g|png|webp|gif)$#';

    public static function clean(string $html): string
    {
        $source = self::load($html);
        $out = new DOMDocument('1.0', 'UTF-8');
        $root = $out->createElement('body');
        $out->appendChild($root);

        self::copyChildren($source, $root, $out);
        self::wrapLooseInline($root, $out);
        self::dropEmpty($root);

        $html = '';
        foreach ($root->childNodes as $child) {
            $html .= $out->saveHTML($child);
        }

        return trim($html);
    }

    /** Plain text (from the form without JavaScript): blank lines separate paragraphs, single line breaks stay. */
    public static function fromText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        $paragraphs = preg_split('/\n{2,}/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode('', array_map(fn ($p) => '<p>' . nl2br(e(trim($p)), false) . '</p>', $paragraphs));
    }

    public static function text(string $html): string
    {
        $html = preg_replace('#<(br|/p|/h3|/h4|/li|/blockquote|/figcaption|/pre)\b[^>]*>#i', '$0 ', $html);

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    public static function excerpt(string $html, int $length = 200): string
    {
        $text = self::text($html);

        if (mb_strlen($text) <= $length) {
            return $text;
        }

        $cut = mb_substr($text, 0, $length);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space > $length * 0.6 ? mb_substr($cut, 0, $space) : $cut, ' ,.;:—-') . '…';
    }

    public static function words(string $html): int
    {
        return count(preg_split('/[\s\p{P}]+/u', self::text($html), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    /** @return string[] "media/posts/..." for every picture, in order */
    public static function images(string $html): array
    {
        preg_match_all('#<img\b[^>]*\bsrc="/?(media/posts/[^"]+)"#i', $html, $matches);

        return array_values(array_unique($matches[1]));
    }

    public static function firstImage(string $html): ?string
    {
        return self::images($html)[0] ?? null;
    }

    // ------------------------------------------------------------------

    private static function load(string $html): DOMElement
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        // the XML declaration makes libxml read the string as UTF-8; the wrapper gives one root to walk
        $doc->loadHTML('<?xml encoding="UTF-8"?><body><div id="__post">' . $html . '</div></body>', LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $doc->getElementById('__post') ?? $doc->createElement('div');
    }

    private static function copyChildren(DOMNode $from, DOMNode $to, DOMDocument $out): void
    {
        foreach (iterator_to_array($from->childNodes) as $node) {
            if ($node instanceof DOMText) {
                $to->appendChild($out->createTextNode($node->data));
                continue;
            }

            if (! $node instanceof DOMElement) {
                continue; // comments, processing instructions
            }

            $name = strtolower($node->nodeName);
            $name = self::RENAME[$name] ?? $name;

            if (in_array($name, self::DROP, true)) {
                continue;
            }

            if (! array_key_exists($name, self::ALLOWED)) {
                self::copyChildren($node, $to, $out); // unknown element: keep its text, lose the element
                continue;
            }

            // blocks never nest inside paragraphs or headings: the browser would split them apart anyway
            if (in_array($name, ['p', 'h3', 'h4'], true) && in_array(strtolower($to->nodeName), ['p', 'h3', 'h4', 'li', 'figcaption', 'a', 'strong', 'em', 'u', 's', 'code'], true)) {
                self::copyChildren($node, $to, $out);
                continue;
            }

            $element = self::element($node, $name, $out);

            if ($element === null) {
                continue;
            }

            $to->appendChild($element);

            if (! in_array($name, ['br', 'hr', 'img'], true)) {
                self::copyChildren($node, $element, $out);
            }
        }
    }

    private static function element(DOMElement $node, string $name, DOMDocument $out): ?DOMElement
    {
        $element = $out->createElement($name);

        if ($name === 'a') {
            $href = self::href($node->getAttribute('href'));

            if ($href === null) {
                return self::unwrapLink($out);
            }

            $element->setAttribute('href', $href);
            $element->setAttribute('rel', 'nofollow ugc noopener');
            $element->setAttribute('target', '_blank');
        }

        if ($name === 'img') {
            $src = trim($node->getAttribute('src'));
            $src = preg_replace('#^https?://[^/]+/#i', '/', $src); // our own absolute URL becomes a path

            if (! preg_match(self::IMAGE_SRC, $src)) {
                return null;
            }

            $element->setAttribute('src', '/' . ltrim($src, '/'));
            $element->setAttribute('alt', mb_substr(trim($node->getAttribute('alt')), 0, 200));
            $element->setAttribute('loading', 'lazy');
            $element->setAttribute('decoding', 'async');
        }

        return $element;
    }

    /** A link we will not keep (javascript:, data:, ...): its text stays, as a plain span. */
    private static function unwrapLink(DOMDocument $out): DOMElement
    {
        return $out->createElement('span');
    }

    private static function href(string $href): ?string
    {
        $href = trim($href);

        if (preg_match('#^(mailto:|tel:)[^\s"<>]+$#i', $href)) {
            return $href;
        }

        return safe_url($href) !== null && filter_var($href, FILTER_VALIDATE_URL) ? $href : null;
    }

    /** Text and inline elements sitting directly in the body (or a quote) go into paragraphs. */
    private static function wrapLooseInline(DOMElement $parent, DOMDocument $out): void
    {
        $paragraph = null;

        foreach (iterator_to_array($parent->childNodes) as $node) {
            $isBlock = $node instanceof DOMElement && in_array($node->nodeName, self::BLOCKS, true);

            if ($isBlock) {
                $paragraph = null;
                if ($node->nodeName === 'blockquote') {
                    self::wrapLooseInline($node, $out);
                }
                continue;
            }

            if ($node instanceof DOMText && trim($node->data) === '' && $paragraph === null) {
                $parent->removeChild($node);
                continue;
            }

            if ($node instanceof DOMElement && $node->nodeName === 'img') {
                $figure = $out->createElement('figure');
                $parent->replaceChild($figure, $node);
                $figure->appendChild($node);
                $paragraph = null;
                continue;
            }

            if ($paragraph === null) {
                $paragraph = $out->createElement('p');
                $parent->insertBefore($paragraph, $node);
            }

            $paragraph->appendChild($node);
        }
    }

    /** Paragraphs, headings and list items with nothing in them (an editor leaves plenty). */
    private static function dropEmpty(DOMElement $root): void
    {
        foreach (['p', 'h3', 'h4', 'li', 'blockquote', 'ul', 'ol', 'figcaption', 'strong', 'em', 'u', 's', 'a', 'span'] as $name) {
            foreach (iterator_to_array($root->getElementsByTagName($name)) as $node) {
                $hasImage = $node->getElementsByTagName('img')->length > 0;

                if (! $hasImage && trim(str_replace("\u{00A0}", ' ', $node->textContent)) === '') {
                    $node->parentNode?->removeChild($node);
                }
            }
        }

        // a span is only left where a link was refused: keep its text, lose the span
        foreach (iterator_to_array($root->getElementsByTagName('span')) as $span) {
            while ($span->firstChild) {
                $span->parentNode->insertBefore($span->firstChild, $span);
            }
            $span->parentNode->removeChild($span);
        }

        foreach (iterator_to_array($root->getElementsByTagName('figure')) as $figure) {
            if ($figure->getElementsByTagName('img')->length === 0) {
                $figure->parentNode?->removeChild($figure);
            }
        }

        foreach (['ul', 'ol'] as $list) {
            foreach (iterator_to_array($root->getElementsByTagName($list)) as $node) {
                if (trim($node->textContent) === '') {
                    $node->parentNode?->removeChild($node);
                }
            }
        }
    }
}
