<?php

declare(strict_types=1);

namespace App\Domains\Orders\Services;

use App\Domains\Orders\DTOs\OrderActorData;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Exceptions\OrderException;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Support\OrderDeadlockRetry;
use App\Domains\Orders\Support\OrderLogger;
use Illuminate\Support\Facades\DB;

final class CancelOrderService
{
    public function __construct(
        private readonly OrderAuthorizationService $authorization,
        private readonly OrderLifecycleService $lifecycle,
        private readonly OrderDeadlockRetry $retry,
        private readonly OrderLogger $logger,
    ) {}

    public function execute(OrderActorData $actor, string $publicId): Order
    {
        return $this->retry->run(function () use ($actor, $publicId): Order {
            return DB::transaction(function () use ($actor, $publicId): Order {
                $order = Order::query()->where('public_id', $publicId)->lockForUpdate()->first();
                if ($order === null) {
                    throw OrderException::notFound();
                }

                $this->authorization->assertCanAccess($order, $actor);

                if ($order->status === OrderStatus::Cancelled) {
                    $this->logger->info('cancellation', [
                        'order_public_id' => $order->public_id,
                        'idempotent' => true,
                    ]);

                    return $order;
                }

                $this->lifecycle->cancelByCustomer($order, $actor->userId());

                return $order->fresh(['items', 'adjustments']) ?? $order;
            });
        });
    }
}
