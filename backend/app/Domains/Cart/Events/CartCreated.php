<?php

declare(strict_types=1);

namespace App\Domains\Cart\Events;

final readonly class CartCreated
{
    public function __construct(
        public string $cartPublicId,
        public ?int $userId,
        public bool $guest,
    ) {}
}
