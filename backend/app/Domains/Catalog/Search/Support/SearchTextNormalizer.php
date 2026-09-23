<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Search\Support;

final class SearchTextNormalizer
{
    public function normalize(?string $value, int $maxLength = 100): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $normalized = $this->toUtf8($value);
        $stripped = preg_replace('/\p{C}+/u', ' ', $normalized);
        $normalized = is_string($stripped) ? $stripped : $normalized;
        if (class_exists(\Normalizer::class)) {
            $formC = \Normalizer::normalize($normalized, \Normalizer::FORM_C);
            if (is_string($formC) && $formC !== '') {
                $normalized = $formC;
            }
        }
        $collapsed = preg_replace('/\s+/u', ' ', $normalized);
        $normalized = trim(is_string($collapsed) ? $collapsed : $normalized);

        if ($maxLength > 0 && mb_strlen($normalized, 'UTF-8') > $maxLength) {
            $normalized = mb_substr($normalized, 0, $maxLength, 'UTF-8');
        }

        return $normalized;
    }

    public function searchable(?string $value, int $maxLength = 240): string
    {
        return mb_strtolower($this->normalize($value, $maxLength), 'UTF-8');
    }

    private function toUtf8(string $value): string
    {
        if (function_exists('mb_scrub')) {
            return mb_scrub($value, 'UTF-8');
        }

        $converted = mb_convert_encoding($value, 'UTF-8', 'UTF-8');

        return is_string($converted) ? $converted : '';
    }
}
