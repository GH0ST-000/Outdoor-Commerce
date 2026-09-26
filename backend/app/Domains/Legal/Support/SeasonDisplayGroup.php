<?php

declare(strict_types=1);

namespace App\Domains\Legal\Support;

/**
 * Display grouping for the public season list. This follows the annex kinds
 * and is not a separate legal category.
 */
final class SeasonDisplayGroup
{
    public static function key(?string $scientificName): string
    {
        $genus = strtolower(strtok(trim((string) $scientificName), ' ') ?: '');

        return match ($genus) {
            'anas', 'anser', 'aythya', 'fulica' => 'waterfowl',
            'coturnix' => 'quail',
            'gallinago' => 'snipe',
            'scolopax' => 'woodcock',
            'columba', 'streptopelia' => 'pigeons',
            default => 'other',
        };
    }

    public static function rank(string $key): int
    {
        return match ($key) {
            'waterfowl' => 0,
            'quail' => 1,
            'snipe' => 2,
            'woodcock' => 3,
            'pigeons' => 4,
            default => 5,
        };
    }
}
