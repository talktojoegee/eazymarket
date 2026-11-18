<?php

namespace App\Support;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

class SafeHtml
{
    private const ROOT_TAG = 'safehtmlroot';

    private const ALLOWED_TAGS = [
        'p', 'br', 'hr', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'del', 'ins',
        'sub', 'sup', 'small', 'mark', 'abbr', 'cite', 'q',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre', 'code',
        'ul', 'ol', 'li', 'dl', 'dt', 'dd',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption', 'colgroup', 'col',
        'a', 'img', 'span', 'div', 'figure', 'figcaption',
    ];

    private const DROPPED_TAGS = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet',
        'form', 'input', 'button', 'select', 'option', 'textarea', 'label', 'fieldset',
        'link', 'meta', 'base', 'svg', 'math', 'template', 'noscript', 'audio', 'video',
        'source', 'track', 'canvas', 'portal', 'dialog',
    ];

    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'td' => ['colspan', 'rowspan', 'headers'],
        'th' => ['colspan', 'rowspan', 'scope', 'headers'],
        'col' => ['span'],
        'colgroup' => ['span'],
        'abbr' => ['title'],
        'q' => ['cite'],
        'blockquote' => ['cite'],
    ];

    private const URL_ATTRIBUTES = ['href', 'src', 'cite'];

    private const ALLOWED_URL_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        $document = new DOMDocument();
        $previousState = libxml_use_internal_errors(true);

        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"?><' . self::ROOT_TAG . '>' . $html . '</' . self::ROOT_TAG . '>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previousState);

        $root = $document->documentElement;

        if (! $loaded || ! $root instanceof DOMElement || $root->tagName !== self::ROOT_TAG) {
            return e($html);
        }

        self::sanitizeChildren($root);

        $output = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $output .= $document->saveHTML($child);
        }

        return $output;
    }

    private static function sanitizeChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $child) {
            self::sanitizeNode($child);
        }
    }

    private static function sanitizeNode(DOMNode $node): void
    {
        if ($node instanceof DOMText) {
            return;
        }

        if ($node instanceof DOMComment || ! $node instanceof DOMElement) {
            $node->parentNode?->removeChild($node);

            return;
        }

        $tag = strtolower($node->tagName);

        if (in_array($tag, self::DROPPED_TAGS, true)) {
            $node->parentNode?->removeChild($node);

            return;
        }

        self::sanitizeChildren($node);

        if (! in_array($tag, self::ALLOWED_TAGS, true)) {
            self::unwrap($node);

            return;
        }

        self::sanitizeAttributes($node, $tag);
    }

    private static function sanitizeAttributes(DOMElement $element, string $tag): void
    {
        $allowed = self::ALLOWED_ATTRIBUTES[$tag] ?? [];

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->nodeName);

            if (! in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->nodeName);

                continue;
            }

            if (in_array($name, self::URL_ATTRIBUTES, true) && ! self::isSafeUrl($attribute->nodeValue)) {
                $element->removeAttribute($attribute->nodeName);
            }
        }

        if ($tag === 'a' && $element->getAttribute('target') !== '') {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private static function isSafeUrl(?string $url): bool
    {
        $url = preg_replace('/[\x00-\x20\x7F]/', '', (string) $url);

        if ($url === null || $url === '') {
            return false;
        }

        $colon = strpos($url, ':');
        $slash = strpos($url, '/');
        $question = strpos($url, '?');
        $hash = strpos($url, '#');

        if ($colon === false) {
            return true;
        }

        foreach ([$slash, $question, $hash] as $position) {
            if ($position !== false && $position < $colon) {
                return true;
            }
        }

        return in_array(strtolower(substr($url, 0, $colon)), self::ALLOWED_URL_SCHEMES, true);
    }

    private static function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if (! $parent) {
            return;
        }

        foreach (iterator_to_array($element->childNodes) as $child) {
            $parent->insertBefore($child, $element);
        }

        $parent->removeChild($element);
    }
}
