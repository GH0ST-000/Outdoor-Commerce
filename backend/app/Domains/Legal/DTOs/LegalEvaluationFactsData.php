<?php

declare(strict_types=1);

namespace App\Domains\Legal\DTOs;

use App\Domains\Legal\Enums\LegalActivityType;

final readonly class LegalEvaluationFactsData
{
    /**
     * @param  list<string>  $permitCodes
     * @param  list<string>  $licenseCodes
     * @param  list<string>  $equipmentCodes
     * @param  list<string>  $methodCodes
     * @param  array<string, scalar|null>  $userAttributes
     */
    public function __construct(
        public LegalActivityType $activityType,
        public string $jurisdictionCode,
        public \DateTimeInterface $occurredAt,
        public ?int $speciesId = null,
        public ?string $regionCode = null,
        public ?string $zoneReference = null,
        public array $permitCodes = [],
        public array $licenseCodes = [],
        public array $equipmentCodes = [],
        public array $methodCodes = [],
        public ?int $requestedQuantity = null,
        public array $userAttributes = [],
    ) {}
}
