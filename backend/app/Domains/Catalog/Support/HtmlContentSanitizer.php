<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Support;

/**
 * Allowlist HTML sanitizer for product descriptions.
 * Strips scripts, event handlers, and unsafe URLs.
 */
final class HtmlContentSanitizer
{
    private const ALLOWED_TAGS = '<p><br><strong><em><b><i><u><ul><ol><li><h2><h3><h4><blockquote><a><span>';

    public function sanitize(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $trimmed = trim($html);
        if ($trimmed === '') {
            return null;
        }

        // Remove script/style blocks early.
        $trimmed = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', '', $trimmed) ?? $trimmed;
        $stripped = strip_tags($trimmed, self::ALLOWED_TAGS);

        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $document->loadHTML(
            '<?xml encoding="UTF-8"><body>'.$stripped.'</body>',
            LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $document->getElementsByTagName('body')->item(0);
        if ($body === null) {
            return $stripped !== '' ? $stripped : null;
        }

        $this->cleanNode($body);

        $output = '';
        foreach ($body->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        $output = trim($output);

        return $output !== '' ? $output : null;
    }

    private function cleanNode(\DOMNode $node): void
    {
        if ($node instanceof \DOMElement) {
            $tag = strtolower($node->tagName);
            $removeAttrs = [];

            foreach (iterator_to_array($node->attributes ?? []) as $attr) {
                $name = strtolower($attr->name);
                $value = $attr->value;

                if (str_starts_with($name, 'on')) {
                    $removeAttrs[] = $name;

                    continue;
                }

                if ($tag === 'a' && $name === 'href') {
                    if (! $this->isSafeUrl($value)) {
                        $removeAttrs[] = $name;
                    }

                    continue;
                }

                if (! in_array($name, ['href', 'title'], true)) {
                    $removeAttrs[] = $name;
                }
            }

            foreach ($removeAttrs as $attrName) {
                $node->removeAttribute($attrName);
            }
        }

        foreach (iterator_to_array($node->childNodes) as $child) {
            $this->cleanNode($child);
        }
    }

    private function isSafeUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '' || str_starts_with($url, '#')) {
            return true;
        }

        if (preg_match('#^(javascript|data|vbscript):#i', $url) === 1) {
            return false;
        }

        return (bool) preg_match('#^(https?:)?//|^/|^mailto:#i', $url);
    }
}
