<?php

declare(strict_types=1);

namespace App\Domains\Identity\Support;

/**
 * Collapses whitespace in human names without touching letter case.
 *
 * Case is preserved because Georgian (Mkhedruli) is unicameral and several
 * Latin surnames carry meaningful internal capitals ("McDonald", "van Dijk").
 */
final class NameNormalizer
{
    public static function normalize(?string $name): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', (string) $name);

        return trim($collapsed ?? (string) $name);
    }
}
