<?php

declare(strict_types=1);

namespace App\Domains\Cart\Actions;

use App\Domains\Cart\DTOs\CartActorData;
use App\Domains\Cart\DTOs\CartMutationResultData;
use App\Domains\Cart\Services\CartService;

final class ClearCartAction
{
    public function __construct(private readonly CartService $carts) {}

    public function execute(CartActorData $actor): CartMutationResultData
    {
        return $this->carts->clear($actor);
    }
}
