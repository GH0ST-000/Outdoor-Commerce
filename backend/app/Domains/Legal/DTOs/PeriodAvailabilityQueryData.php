<?php

declare(strict_types=1);

namespace App\Domains\Legal\DTOs;

use App\Domains\Legal\Enums\AvailabilityMode;
use App\Domains\Legal\Enums\LegalActivityType;
use App\Domains\Legal\Support\SeasonDateRange;

final readonly class PeriodAvailabilityQueryData
{
    /**
     * @param  list<string>  $permitCodes
     * @param  list<string>  $licenseCodes
     * @param  list<string>  $methodCodes
     * @param  list<string>  $equipmentCodes
     */
    public function __construct(
        public LegalActivityType $activityType,
        public SeasonDateRange $period,
        public AvailabilityMode $mode,
        public string $jurisdictionCode,
        public ?string $regionCode = null,
        public ?string $zoneReference = null,
        public ?int $speciesId = null,
        public ?int $speciesCategoryId = null,
        public array $permitCodes = [],
        public array $licenseCodes = [],
        public array $methodCodes = [],
        public array $equipmentCodes = [],
        public ?int $requestedQuantity = null,
        public bool $includeTrace = false,
    ) {}
}
