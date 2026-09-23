<?php

declare(strict_types=1);

namespace App\Domains\Cart\Events;

final readonly class CartPriceChanged
{
    public function __construct(
        public string $cartPublicId,
        public string $itemPublicId,
        public int $previousUnitMinor,
        public int $currentUnitMinor,
    ) {}
}
