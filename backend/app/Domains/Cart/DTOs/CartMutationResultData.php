<?php

declare(strict_types=1);

namespace App\Domains\Cart\DTOs;

final readonly class CartMutationResultData
{
    public function __construct(
        public CartSnapshotData $cart,
        public ?string $issuedGuestToken = null,
        public bool $forgetGuestCookie = false,
    ) {}
}
