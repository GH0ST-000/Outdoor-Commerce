<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Events;

final readonly class CatalogMediaChanged
{
    public function __construct(
        public ?int $productId,
        public ?int $variantId,
    ) {}
}
