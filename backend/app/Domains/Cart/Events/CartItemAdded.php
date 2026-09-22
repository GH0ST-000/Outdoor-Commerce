<?php

declare(strict_types=1);

namespace App\Domains\Cart\Events;

final readonly class CartItemAdded
{
    public function __construct(
        public string $cartPublicId,
        public string $itemPublicId,
        public int $variantId,
        public int $quantity,
    ) {}
}
