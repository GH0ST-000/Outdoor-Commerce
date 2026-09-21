<?php

declare(strict_types=1);

namespace App\Domains\Pricing\DTOs;

use Carbon\CarbonImmutable;

final readonly class BulkUpsertPriceItemData
{
    public function __construct(
        public int $priceListId,
        public int $productVariantId,
        public int $amountMinor,
        public ?CarbonImmutable $startsAt = null,
        public ?int $expectedVersion = null,
    ) {}
}
