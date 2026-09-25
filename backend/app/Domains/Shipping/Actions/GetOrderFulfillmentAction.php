<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Actions;

use App\Domains\Orders\Actions\GetOrderAction;
use App\Domains\Orders\DTOs\OrderActorData;
use App\Domains\Orders\Models\Order;

final class GetOrderFulfillmentAction
{
    public function __construct(private readonly GetOrderAction $orders) {}

    public function execute(OrderActorData $actor, string $orderPublicId): Order
    {
        return $this->orders->execute($actor, $orderPublicId);
    }
}
