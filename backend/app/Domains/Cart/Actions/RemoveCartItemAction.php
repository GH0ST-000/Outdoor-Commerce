<?php

declare(strict_types=1);

namespace App\Domains\Cart\Actions;

use App\Domains\Cart\DTOs\CartMutationResultData;
use App\Domains\Cart\DTOs\RemoveCartItemData;
use App\Domains\Cart\Services\CartService;

final class RemoveCartItemAction
{
    public function __construct(private readonly CartService $carts) {}

    public function execute(RemoveCartItemData $data): CartMutationResultData
    {
        return $this->carts->removeItem($data);
    }
}
