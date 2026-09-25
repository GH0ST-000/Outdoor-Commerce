<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Support;

final class AliasNormalizer
{
    public static function normalize(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (class_exists(\Normalizer::class)) {
            $nfc = \Normalizer::normalize($value, \Normalizer::FORM_C);
            if (is_string($nfc) && $nfc !== '') {
                $value = $nfc;
            }
        }

        $collapsed = preg_replace('/\s+/u', ' ', $value);
        $value = is_string($collapsed) ? $collapsed : $value;

        return mb_strtolower(trim($value), 'UTF-8');
    }
}
