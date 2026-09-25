<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Support;

/**
 * Allowlist HTML sanitizer for species editorial fields.
 */
final class SpeciesHtmlSanitizer
{
    private const ALLOWED_TAGS = '<p><br><strong><em><b><i><u><ul><ol><li><h2><h3><h4><blockquote><a>';

    public function sanitize(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $trimmed = trim($html);
        if ($trimmed === '') {
            return null;
        }

        $trimmed = preg_replace('#<(script|style|iframe|object|embed)[^>]*>.*?</\1>#is', '', $trimmed) ?? $trimmed;
        $stripped = strip_tags($trimmed, self::ALLOWED_TAGS);

        if (! str_contains($stripped, '<')) {
            return $stripped !== '' ? $stripped : null;
        }

        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $document->loadHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'.$stripped.'</body></html>',
            LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $document->getElementsByTagName('body')->item(0);
        if ($body === null) {
            return $stripped;
        }

        $this->cleanNode($body);

        $output = '';
        foreach ($body->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        $output = trim($output);

        return $output !== '' ? $output : null;
    }

    public function containsUnsafe(string $html): bool
    {
        if (preg_match('#<(script|iframe|object|embed|form)[\s>]#i', $html) === 1) {
            return true;
        }

        return preg_match('/\son[a-z]+\s*=/i', $html) === 1;
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

                if (! in_array($name, ['href', 'title', 'lang'], true)) {
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

        return (bool) preg_match('#^(https?:)?//|^/#i', $url);
    }
}
