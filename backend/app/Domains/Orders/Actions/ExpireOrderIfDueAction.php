<?php

declare(strict_types=1);

namespace App\Domains\Orders\Actions;

use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Services\OrderLifecycleService;

final class ExpireOrderIfDueAction
{
    public function __construct(private readonly OrderLifecycleService $lifecycle) {}

    public function execute(Order $order, bool $requestTime = false): Order
    {
        return $this->lifecycle->expireIfDue($order, $requestTime);
    }
}
