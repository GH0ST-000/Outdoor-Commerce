<?php

declare(strict_types=1);

namespace App\Domains\Orders\Actions;

use App\Domains\Orders\DTOs\OrderActorData;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Services\OrderAuthorizationService;

final class AssertOrderAccessAction
{
    public function __construct(private readonly OrderAuthorizationService $authorization) {}

    public function execute(Order $order, OrderActorData $actor): void
    {
        $this->authorization->assertCanAccess($order, $actor);
    }
}
