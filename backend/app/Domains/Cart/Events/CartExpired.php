<?php

declare(strict_types=1);

namespace App\Domains\Cart\Events;

final readonly class CartExpired
{
    public function __construct(
        public string $cartPublicId,
    ) {}
}
