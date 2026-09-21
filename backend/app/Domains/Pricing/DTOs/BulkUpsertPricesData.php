<?php

declare(strict_types=1);

namespace App\Domains\Pricing\DTOs;

final readonly class BulkUpsertPricesData
{
    /**
     * @param  list<BulkUpsertPriceItemData>  $items
     */
    public function __construct(
        public array $items,
        public bool $publish = true,
    ) {}
}
