<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Events;

final readonly class CatalogVariantChanged
{
    public function __construct(
        public int $variantId,
        public int $productId,
    ) {}
}
