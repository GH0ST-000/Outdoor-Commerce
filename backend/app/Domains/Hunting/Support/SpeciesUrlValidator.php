<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Support;

final class SpeciesUrlValidator
{
    public static function isSafe(?string $url): bool
    {
        if ($url === null || trim($url) === '') {
            return true;
        }

        $url = trim($url);
        if (preg_match('#^(javascript|data|vbscript):#i', $url) === 1) {
            return false;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true);
    }
}
