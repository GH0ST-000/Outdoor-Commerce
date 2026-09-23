<?php

declare(strict_types=1);

namespace App\Domains\Cart\Events;

final readonly class CartItemRemoved
{
    public function __construct(
        public string $cartPublicId,
        public string $itemPublicId,
        public int $variantId,
    ) {}
}
