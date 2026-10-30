<?php
declare(strict_types=1);

namespace App\Services;

use App\Validators\Validator;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allow-list HTML sanitiser for editor content (pages, blog posts, product descriptions).
 * Anything not explicitly allowed — tags, attributes, URL schemes, classes, inline styles,
 * event handlers — is removed. Disallowed tags keep their text content.
 */
final class HtmlSanitizer
{
    private const TAGS = [
        'p' => [], 'br' => [], 'hr' => [], 'h2' => ['id'], 'h3' => ['id'], 'h4' => ['id'],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [], 'mark' => [], 'small' => [], 'sub' => [], 'sup' => [],
        'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [], 'code' => [], 'pre' => [],
        'a' => ['href', 'title', 'target', 'rel', 'class'],
        'img' => ['src', 'alt', 'width', 'height', 'loading', 'title'],
        'figure' => ['class'], 'figcaption' => [],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => ['colspan', 'rowspan', 'scope'], 'td' => ['colspan', 'rowspan'],
        'div' => ['class', 'data-product-id'], 'span' => ['class'],
    ];
    /** Tags whose whole subtree is dropped. */
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'svg', 'math', 'template', 'noscript', 'link', 'meta', 'base', 'frame', 'frameset'];
    private const CLASSES = ['note-box', 'warning-box', 'product-embed', 'cta-button', 'image-full', 'image-center',
        // inline image size and placement from the editor's image dialog
        'img-full', 'img-medium', 'img-small', 'img-center', 'img-start', 'img-end'];

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }
        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="__root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('__root');
        if (!$root) {
            return '';
        }
        self::walk($root);
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        return trim($out);
    }

    private static function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
                $node->removeChild($child);
                continue;
            }
            if (!$child instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROP, true)) {
                $node->removeChild($child);
                continue;
            }
            if (!array_key_exists($tag, self::TAGS)) {
                self::walk($child);
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }
            self::cleanAttributes($child, self::TAGS[$tag]);
            self::walk($child);
        }
    }

    private static function cleanAttributes(DOMElement $el, array $allowed): void
    {
        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->name);
            $value = trim($attr->value);
            if (!in_array($name, $allowed, true)) {
                $el->removeAttribute($attr->name);
                continue;
            }
            $keep = match ($name) {
                'href' => Validator::isUrl($value),
                'src' => Validator::isLocalPath(ltrim(preg_replace('#^/[^/]*/(?=(assets|uploads)/)#', '', $value) ?? '', '/')) || preg_match('#^https://#i', $value) === 1,
                'target' => $value === '_blank',
                'rel', 'title', 'alt' => true,
                'width', 'height', 'colspan', 'rowspan', 'data-product-id' => ctype_digit($value) && (int) $value < 10000,
                'loading' => $value === 'lazy',
                'scope' => in_array($value, ['row', 'col'], true),
                'id' => (bool) preg_match('/^[\p{L}\p{N}_-]{1,80}$/u', $value),
                'class' => true,
                default => false,
            };
            if (!$keep) {
                $el->removeAttribute($attr->name);
            } elseif ($name === 'class') {
                $classes = array_values(array_intersect(preg_split('/\s+/', $value) ?: [], self::CLASSES));
                if ($classes) {
                    $el->setAttribute('class', implode(' ', $classes));
                } else {
                    $el->removeAttribute('class');
                }
            }
        }
        if (strtolower($el->tagName) === 'a' && $el->getAttribute('target') === '_blank') {
            $el->setAttribute('rel', 'noopener noreferrer');
        }
        if (strtolower($el->tagName) === 'img') {
            $el->setAttribute('loading', 'lazy');
            if (!$el->hasAttribute('alt')) {
                $el->setAttribute('alt', '');
            }
        }
    }

    /** Plain text for excerpts, reading time and search. */
    public static function toText(?string $html): string
    {
        $text = html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '</li>', '</h2>', '</h3>'], ' ', (string) $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }
}
