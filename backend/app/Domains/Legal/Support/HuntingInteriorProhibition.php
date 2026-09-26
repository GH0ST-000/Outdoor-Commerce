<?php

declare(strict_types=1);

namespace App\Domains\Legal\Support;

/**
 * Order No. 95 article 3 paragraph 7 and the 24 July 2026 ministry text
 * both prohibit hunting inside state nature reserves and national parks.
 * They disagree on the surrounding distance, so no buffer is generated.
 */
final class HuntingInteriorProhibition
{
    /**
     * @var list<string>
     */
    public const ZONE_TYPES = [
        'national_park',
        'strict_nature_reserve',
    ];

    public static function applies(string $activity, string $zoneType): bool
    {
        return $activity === 'hunting' && in_array($zoneType, self::ZONE_TYPES, true);
    }
}
