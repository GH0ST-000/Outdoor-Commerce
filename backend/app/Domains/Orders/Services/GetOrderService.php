<?php

declare(strict_types=1);

namespace App\Domains\Orders\Services;

use App\Domains\Orders\DTOs\OrderActorData;
use App\Domains\Orders\Exceptions\OrderException;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Support\OrderDeadlockRetry;
use App\Domains\Shared\Support\Clock;
use Illuminate\Support\Facades\DB;

final class GetOrderService
{
    public function __construct(
        private readonly OrderAuthorizationService $authorization,
        private readonly OrderLifecycleService $lifecycle,
        private readonly OrderDeadlockRetry $retry,
        private readonly Clock $clock,
    ) {}

    public function execute(OrderActorData $actor, string $publicId): Order
    {
        $order = Order::query()->where('public_id', $publicId)->first();
        if ($order === null) {
            throw OrderException::notFound();
        }

        $this->authorization->assertCanAccess($order, $actor);

        if ($order->reservation_expires_at !== null && $order->reservation_expires_at->lte($this->clock->now())) {
            $order = $this->retry->run(function () use ($publicId): Order {
                return DB::transaction(function () use ($publicId): Order {
                    $locked = Order::query()->where('public_id', $publicId)->lockForUpdate()->first();
                    if ($locked === null) {
                        throw OrderException::notFound();
                    }

                    return $this->lifecycle->expireIfDue($locked, true);
                });
            });
        }

        return $order->fresh(['items', 'adjustments']) ?? $order;
    }
}
