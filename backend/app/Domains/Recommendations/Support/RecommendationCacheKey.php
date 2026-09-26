<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Support;

/**
 * Public cache keys use derived signals only. Coordinate fields are dropped
 * even if a caller accidentally includes them.
 */
final class RecommendationCacheKey
{
    /**
     * @var list<string>
     */
    private const DROPPED = [
        'lat',
        'lng',
        'latitude',
        'longitude',
        'coordinate',
        'coordinates',
        'pin',
        'accuracy',
    ];

    /**
     * @param  array<string, mixed>  $parts
     */
    public function make(array $parts): string
    {
        $clean = $this->strip($parts);
        $encoded = json_encode($clean, JSON_THROW_ON_ERROR);

        return 'rec:v1:'.hash('sha256', $encoded);
    }

    /**
     * @param  array<string, mixed>  $parts
     * @return array<string, mixed>
     */
    public function strip(array $parts): array
    {
        $clean = [];
        foreach ($parts as $key => $value) {
            if (in_array(strtolower((string) $key), self::DROPPED, true)) {
                continue;
            }
            if (is_array($value)) {
                if (array_is_list($value)) {
                    $clean[(string) $key] = $value;

                    continue;
                }
                /** @var array<string, mixed> $value */
                $clean[(string) $key] = $this->strip($value);

                continue;
            }
            $clean[(string) $key] = $value;
        }
        ksort($clean);

        return $clean;
    }
}
