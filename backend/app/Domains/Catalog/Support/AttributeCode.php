<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Support;

final class AttributeCode
{
    public static function normalize(string $code): string
    {
        $normalized = strtolower(trim($code));
        $normalized = preg_replace('/[^a-z0-9_]+/', '_', $normalized) ?? '';
        $normalized = trim($normalized, '_');

        return $normalized;
    }

    public static function isValid(string $code): bool
    {
        return $code !== '' && (bool) preg_match('/^[a-z][a-z0-9_]{0,62}$/', $code);
    }
}
