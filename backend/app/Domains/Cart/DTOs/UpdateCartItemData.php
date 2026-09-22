<?php

declare(strict_types=1);

namespace App\Domains\Cart\DTOs;

final readonly class UpdateCartItemData
{
    public function __construct(
        public CartActorData $actor,
        public string $itemPublicId,
        public int $quantity,
    ) {}
}
