<?php

declare(strict_types=1);

namespace App\Domains\Cart\Events;

final readonly class CartCleared
{
    public function __construct(
        public string $cartPublicId,
        public int $removedCount,
    ) {}
}
