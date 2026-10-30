<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

class RichTextSanitizer
{
    /** @var array<string, list<string>> */
    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'title'],
    ];

    /** @var list<string> */
    private const ALLOWED_TAGS = [
        'a', 'br', 'em', 'h2', 'h3', 'li', 'ol', 'p', 'strong', 'u', 'ul',
    ];

    private const DROP_TAGS = [
        'script', 'style', 'svg', 'math', 'iframe', 'object', 'embed', 'template',
        'noscript', 'textarea', 'title', 'xmp', 'plaintext', 'noembed', 'noframes',
    ];

    private const HTML_PATTERN = '/<\/?(a|br|em|h[1-6]|li|ol|p|strong|u|ul|div|span|section|article|b|i|s|code|pre|blockquote|table|tbody|tr|td|th|img|input|form|button|select|option|video|audio|source|link|meta|base|script|style|svg|math|iframe|object|embed|template|noscript|textarea|title|xmp|plaintext|noembed|noframes)\b|<!--|^\s*<[^>]+>\s*$/i';

    public function sanitize(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        if (! preg_match(self::HTML_PATTERN, $html) && ! preg_match('/<([a-z][a-z0-9:-]*)\b[^>]*>[\s\S]*<\/\1\s*>/i', $html)) {
            return htmlspecialchars(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>',
            LIBXML_NONET | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementsByTagName('body')->item(0);

        if (! $root) {
            return '';
        }

        return trim($this->sanitizeChildren($root));
    }

    public function toPlainText(?string $html): string
    {
        $sanitized = $this->sanitize($html);

        if ($sanitized === '') {
            return '';
        }

        $text = strip_tags(str_replace(['</p>', '</h2>', '</h3>', '</li>', '<br>'], ["\n", "\n", "\n", "\n", "\n"], $sanitized));

        return trim((string) preg_replace(
            '/[\s\p{Z}\p{Cf}]+/u',
            ' ',
            html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')
        ));
    }

    private function sanitizeChildren(DOMNode $parent, int $depth = 0): string
    {
        if ($depth >= 64) {
            return '';
        }

        $result = '';
        foreach (iterator_to_array($parent->childNodes) as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $result .= htmlspecialchars($child->nodeValue ?? '', ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP_TAGS, true)) {
                continue;
            }

            $content = $this->sanitizeChildren($child, $depth + 1);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                $result .= $content;

                continue;
            }

            $attributes = '';
            if ($tag === 'a') {
                foreach (self::ALLOWED_ATTRIBUTES['a'] as $name) {
                    $value = trim($child->getAttribute($name));
                    if ($value === '' || ($name === 'href' && (! preg_match('/^(https?:|mailto:)/i', $value) || preg_match('/[\x00-\x20\x7f]/', $value)))) {
                        continue;
                    }
                    $attributes .= ' '.$name.'="'.htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8').'"';
                }
            }

            $result .= $tag === 'br' ? '<br>' : '<'.$tag.$attributes.'>'.$content.'</'.$tag.'>';
        }

        return $result;
    }
}
