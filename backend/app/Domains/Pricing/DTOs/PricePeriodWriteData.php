<?php

declare(strict_types=1);

namespace App\Domains\Pricing\DTOs;

use Carbon\CarbonImmutable;

final readonly class PricePeriodWriteData
{
    public function __construct(
        public int $amountMinor,
        public CarbonImmutable $startsAt,
        public ?CarbonImmutable $endsAt = null,
        public ?int $expectedVersion = null,
    ) {}
}
