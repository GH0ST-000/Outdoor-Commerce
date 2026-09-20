<?php

declare(strict_types=1);

namespace App\Domains\Pricing\DTOs;

use App\Domains\Pricing\Enums\PriceListStatus;

final readonly class PriceListWriteData
{
    public function __construct(
        public string $code,
        public string $name,
        public string $currencyCode,
        public PriceListStatus $status,
        public bool $isDefault,
        public int $priority,
        public bool $pricesIncludeTax,
    ) {}
}
