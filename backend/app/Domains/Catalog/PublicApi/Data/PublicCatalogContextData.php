<?php

declare(strict_types=1);

namespace App\Domains\Catalog\PublicApi\Data;

use Carbon\CarbonImmutable;

final readonly class PublicCatalogContextData
{
    public function __construct(
        public string $locale,
        public string $fallbackLocale,
        public string $currency,
        public int $priceListId,
        public CarbonImmutable $effectiveAt,
        public bool $usedFallback = false,
        public mixed $customerContext = null,
    ) {}

    /**
     * @return array{locale: string, currency: string, price_list_id: int}
     */
    public function cacheParts(): array
    {
        return [
            'locale' => $this->locale,
            'currency' => $this->currency,
            'price_list_id' => $this->priceListId,
        ];
    }
}
