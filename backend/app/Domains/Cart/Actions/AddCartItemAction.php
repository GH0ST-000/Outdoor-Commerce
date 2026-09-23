<?php

declare(strict_types=1);

namespace App\Domains\Cart\Actions;

use App\Domains\Cart\DTOs\AddCartItemData;
use App\Domains\Cart\DTOs\CartMutationResultData;
use App\Domains\Cart\Services\CartService;

final class AddCartItemAction
{
    public function __construct(private readonly CartService $carts) {}

    public function execute(AddCartItemData $data): CartMutationResultData
    {
        return $this->carts->addItem($data);
    }
}
