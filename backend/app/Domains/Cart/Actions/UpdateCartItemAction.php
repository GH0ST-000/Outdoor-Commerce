<?php

declare(strict_types=1);

namespace App\Domains\Cart\Actions;

use App\Domains\Cart\DTOs\CartMutationResultData;
use App\Domains\Cart\DTOs\UpdateCartItemData;
use App\Domains\Cart\Services\CartService;

final class UpdateCartItemAction
{
    public function __construct(private readonly CartService $carts) {}

    public function execute(UpdateCartItemData $data): CartMutationResultData
    {
        return $this->carts->updateItem($data);
    }
}
