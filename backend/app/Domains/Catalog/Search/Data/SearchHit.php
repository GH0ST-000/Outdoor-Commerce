<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Search\Data;

final readonly class SearchHit
{
    public function __construct(
        public int $productId,
        public int $variantId,
        public float $rankingScore,
    ) {}
}
