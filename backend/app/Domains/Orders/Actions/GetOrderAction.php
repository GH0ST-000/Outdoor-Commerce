<?php

declare(strict_types=1);

namespace App\Domains\Orders\Actions;

use App\Domains\Orders\DTOs\OrderActorData;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Services\GetOrderService;

final class GetOrderAction
{
    public function __construct(private readonly GetOrderService $orders) {}

    public function execute(OrderActorData $actor, string $orderPublicId): Order
    {
        return $this->orders->execute($actor, $orderPublicId);
    }
}
