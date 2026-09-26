<?php

declare(strict_types=1);

namespace App\Domains\Geography\DTOs;

use DateTimeInterface;

final readonly class PointLookupQueryData
{
    /**
     * @param  list<string>|null  $zoneTypes
     */
    public function __construct(
        public float $longitude,
        public float $latitude,
        public DateTimeInterface $at,
        public string $jurisdictionCode,
        public ?array $zoneTypes = null,
        public ?string $activity = null,
        public ?int $speciesId = null,
    ) {}
}
