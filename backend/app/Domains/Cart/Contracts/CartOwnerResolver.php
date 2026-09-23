<?php

declare(strict_types=1);

namespace App\Domains\Cart\Contracts;

use App\Domains\Cart\DTOs\CartActorData;
use App\Domains\Cart\Models\Cart;

interface CartOwnerResolver
{
    public function hashGuestToken(?string $rawToken): ?string;

    public function findActive(CartActorData $actor): ?Cart;

    public function lockActive(Cart $cart): Cart;
}
