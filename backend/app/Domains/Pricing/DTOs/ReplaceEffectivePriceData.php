<?php

declare(strict_types=1);

namespace App\Domains\Pricing\DTOs;

use Carbon\CarbonImmutable;

final readonly class ReplaceEffectivePriceData
{
    public function __construct(
        public int $amountMinor,
        public CarbonImmutable $startsAt,
        public ?CarbonImmutable $endsAt,
        public int $expectedVersion,
        public bool $closePreviousAtStart = true,
    ) {}
}
