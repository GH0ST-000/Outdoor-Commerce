<?php

declare(strict_types=1);

namespace App\Domains\Orders\Actions;

use App\Domains\Orders\DTOs\OrderActorData;
use App\Domains\Orders\DTOs\OrderMutationResultData;
use App\Domains\Orders\Services\CreateOrderFromQuoteService;

final class CreateOrderFromQuoteAction
{
    public function __construct(private readonly CreateOrderFromQuoteService $orders) {}

    public function execute(
        OrderActorData $actor,
        string $checkoutSessionPublicId,
        string $quotePublicId,
    ): OrderMutationResultData {
        return $this->orders->execute($actor, $checkoutSessionPublicId, $quotePublicId);
    }
}
