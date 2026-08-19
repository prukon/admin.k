<?php

declare(strict_types=1);

namespace App\Services\InAppNotifications;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;
use Illuminate\Support\Str;

final class InAppNotificationBodyHtml
{
    /** @var list<string> */
    private const ALLOWED_TAGS = ['p', 'br', 'a', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li'];

    /** @var list<string> */
    private const DROP_TAGS = [
        'script', 'style', 'iframe', 'object', 'embed', 'link', 'meta',
        'form', 'input', 'button', 'textarea', 'svg', 'video', 'audio',
    ];

    public static function sanitize(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        if (! preg_match('/<\s*\/?\s*[a-zA-Z]/', $html)) {
            return $html;
        }

        return self::sanitizeMarkup($html);
    }

    public static function toDisplayHtml(?string $html): string
    {
        $clean = self::sanitize($html);
        if ($clean === '') {
            return '';
        }

        if (! preg_match('/<\s*\/?\s*[a-zA-Z]/', $clean)) {
            return nl2br(htmlspecialchars($clean, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false);
        }

        return $clean;
    }

    public static function isBlank(?string $html): bool
    {
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\x{00A0}/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text) === '';
    }

    public static function preview(?string $html, int $limit = 60): string
    {
        return Str::limit(self::toPlainTextWithBreaks((string) $html), $limit);
    }

    /**
     * Текст для выпадашки: абзацы/переносы остаются переводами строк,
     * горизонтальные пробелы схлопываются, HTML снимается.
     */
    public static function toPlainTextWithBreaks(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $html = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<\s*\/\s*(p|div|h[1-6]|li|blockquote|tr)\s*>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<\s*\/\s*(ul|ol)\s*>/i', "\n", $html) ?? $html;

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\x{00A0}/u', ' ', $text) ?? $text;
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[^\S\n]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */u', "\n", $text) ?? $text;
        $text = preg_replace("/\n{2,}/u", "\n", $text) ?? $text;

        return trim($text);
    }

    private static function sanitizeMarkup(string $html): string
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $wrapped = '<?xml encoding="UTF-8"><div id="in-app-body-root">'.$html.'</div>';
        $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);
        $root = $xpath->query('//*[@id="in-app-body-root"]')->item(0);
        if (! $root instanceof DOMElement) {
            return '';
        }

        self::sanitizeNode($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return trim($out);
    }

    private static function sanitizeNode(DOMNode $node): void
    {
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child instanceof DOMText) {
                continue;
            }

            if ($child->nodeType === XML_COMMENT_NODE) {
                $child->parentNode?->removeChild($child);
                continue;
            }

            if (! $child instanceof DOMElement) {
                $child->parentNode?->removeChild($child);
                continue;
            }

            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROP_TAGS, true)) {
                $child->parentNode?->removeChild($child);
                continue;
            }

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                self::sanitizeNode($child);
                self::unwrap($child);
                continue;
            }

            self::sanitizeAttributes($child);
            self::sanitizeNode($child);

            if ($tag === 'a' && ! $child->hasAttribute('href')) {
                self::unwrap($child);
            }
        }
    }

    private static function sanitizeAttributes(DOMElement $el): void
    {
        $tag = strtolower($el->tagName);
        $href = $tag === 'a' ? trim((string) $el->getAttribute('href')) : '';

        $names = [];
        foreach ($el->attributes as $attr) {
            $names[] = $attr->name;
        }
        foreach ($names as $name) {
            $el->removeAttribute($name);
        }

        if ($tag !== 'a') {
            return;
        }

        $safe = InAppNotificationActionUrl::sanitize($href);
        if ($safe === null) {
            return;
        }

        $el->setAttribute('href', $safe);
        if (str_starts_with($safe, 'http://') || str_starts_with($safe, 'https://')) {
            $el->setAttribute('target', '_blank');
            $el->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private static function unwrap(DOMElement $el): void
    {
        $parent = $el->parentNode;
        if ($parent === null) {
            return;
        }

        while ($el->firstChild) {
            $parent->insertBefore($el->firstChild, $el);
        }

        $parent->removeChild($el);
    }
}
