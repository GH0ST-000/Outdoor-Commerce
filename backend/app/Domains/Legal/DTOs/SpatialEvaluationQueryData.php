<?php

declare(strict_types=1);

namespace App\Domains\Legal\DTOs;

use App\Domains\Legal\Enums\LegalActivityType;
use DateTimeInterface;

final readonly class SpatialEvaluationQueryData
{
    /**
     * @param  list<string>  $permitCodes
     * @param  list<string>  $licenseCodes
     * @param  list<string>  $methodCodes
     * @param  list<string>  $equipmentCodes
     */
    public function __construct(
        public float $longitude,
        public float $latitude,
        public DateTimeInterface $occurredAt,
        public LegalActivityType $activityType,
        public string $jurisdictionCode,
        public ?int $speciesId = null,
        public ?string $from = null,
        public ?string $to = null,
        public ?string $availabilityMode = null,
        public array $permitCodes = [],
        public array $licenseCodes = [],
        public array $methodCodes = [],
        public array $equipmentCodes = [],
    ) {}
}
