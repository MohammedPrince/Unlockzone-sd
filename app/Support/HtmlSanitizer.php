<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Keeps the formatting the description editor can produce (text styles, lists, links, images, tables)
 * and removes everything else, so stored HTML is safe to print on customer pages.
 */
class HtmlSanitizer
{
    /** Allowed tags and the attributes each one may keep. */
    private const ALLOWED = [
        'p' => [], 'br' => [], 'hr' => [], 'div' => [], 'span' => [],
        'b' => [], 'strong' => [], 'i' => [], 'em' => [], 'u' => [], 's' => [], 'strike' => [],
        'sub' => [], 'sup' => [], 'code' => [], 'pre' => [], 'blockquote' => [],
        'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [],
        'ul' => [], 'ol' => [], 'li' => [],
        'a' => ['href', 'target'],
        'img' => ['src', 'alt', 'width', 'height'],
        'table' => [], 'thead' => [], 'tbody' => [], 'tfoot' => [], 'tr' => [], 'caption' => [],
        'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
    ];

    /** Removed together with everything inside them. */
    private const DROPPED = [
        'script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'select', 'textarea',
        'link', 'meta', 'base', 'svg', 'math', 'noscript', 'template', 'frame', 'frameset', 'applet', 'audio', 'video',
    ];

    public static function clean(?string $html): ?string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return null;
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><html><body><div>' . $html . '</div></body></html>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $document->getElementsByTagName('body')->item(0);

        if (!$body) {
            return null;
        }

        self::walk($body);

        $out = '';
        foreach ($body->childNodes as $wrapper) {
            foreach ($wrapper->childNodes as $child) {
                $out .= $document->saveHTML($child);
            }
        }
        $out = trim($out);

        // An editor left empty still sends "<p><br></p>".
        $hasText = trim(html_entity_decode(strip_tags($out), ENT_QUOTES | ENT_HTML5, 'UTF-8'), " \t\n\r\0\x0B\u{00A0}") !== '';
        $hasMedia = stripos($out, '<img') !== false || stripos($out, '<table') !== false;

        return ($hasText || $hasMedia) ? $out : null;
    }

    private static function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMText) {
                continue;
            }

            if (!$child instanceof DOMElement) {
                // Comments, processing instructions, CDATA.
                $node->removeChild($child);
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROPPED, true)) {
                $node->removeChild($child);
                continue;
            }

            self::walk($child);

            if (!array_key_exists($tag, self::ALLOWED)) {
                // Unknown tag: keep what is inside it, drop the tag itself.
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            self::cleanAttributes($child, $tag);

            if ($tag === 'img' && !$child->hasAttribute('src')) {
                $node->removeChild($child);
            }
        }
    }

    private static function cleanAttributes(DOMElement $element, string $tag): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);
            $value = trim($attribute->value);

            if ($name === 'style') {
                $style = self::cleanStyle($value);
                $style === '' ? $element->removeAttribute($attribute->name) : $element->setAttribute('style', $style);
                continue;
            }

            if ($name === 'dir' && in_array(strtolower($value), ['ltr', 'rtl', 'auto'], true)) {
                continue;
            }

            if (!in_array($name, self::ALLOWED[$tag], true)) {
                $element->removeAttribute($attribute->name);
                continue;
            }

            $valid = match ($name) {
                'href' => self::safeUrl($value, ['http', 'https', 'mailto', 'tel']),
                'src' => self::safeUrl($value, ['http', 'https']),
                'target' => $value === '_blank',
                'width', 'height', 'colspan', 'rowspan' => ctype_digit($value) && (int) $value > 0 && (int) $value <= 4000,
                default => true,
            };

            if (!$valid) {
                $element->removeAttribute($attribute->name);
            }
        }

        if ($tag === 'a') {
            $element->setAttribute('rel', 'noopener noreferrer nofollow');
        }
    }

    /** Only alignment survives from inline styles. */
    private static function cleanStyle(string $style): string
    {
        if (preg_match('/(?:^|;)\s*text-align\s*:\s*(left|right|center|justify|start|end)\s*(?:;|$)/i', $style, $m)) {
            return 'text-align: ' . strtolower($m[1]);
        }

        return '';
    }

    /** Absolute URLs with an allowed scheme, or a path on this site. Blocks javascript:, data: and "//host". */
    private static function safeUrl(string $url, array $schemes): bool
    {
        // Browsers ignore control characters and whitespace inside a scheme, so remove them before checking.
        $url = preg_replace('/[\x00-\x20\x7F]+/', '', $url);

        if ($url === '') {
            return false;
        }

        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $url, $m)) {
            return in_array(strtolower($m[1]), $schemes, true);
        }

        return !str_starts_with($url, '//') && !str_starts_with($url, '\\');
    }
}
