<?php

declare(strict_types=1);

namespace App\Domains\Cart\DTOs;

final readonly class AddCartItemData
{
    public function __construct(
        public CartActorData $actor,
        public int $variantId,
        public int $quantity,
        public ?int $productId = null,
    ) {}
}
