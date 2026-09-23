<?php

declare(strict_types=1);

namespace App\Domains\Orders\Services;

use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Support\OrderLogger;
use App\Domains\Shared\Support\Clock;
use Illuminate\Support\Facades\DB;

final class ExpireUnpaidOrdersService
{
    public function __construct(
        private readonly OrderLifecycleService $lifecycle,
        private readonly Clock $clock,
        private readonly OrderLogger $logger,
    ) {}

    /**
     * @return array{expired: int, skipped: int}
     */
    public function execute(?int $chunkSize = null): array
    {
        $chunkSize = $chunkSize ?? max(1, (int) config('order.expire_chunk_size', 100));
        $expired = 0;
        $skipped = 0;

        $ids = Order::query()
            ->whereIn('status', [OrderStatus::PendingPayment->value, OrderStatus::PaymentProcessing->value])
            ->whereNotIn('payment_status', [PaymentStatus::Paid->value, PaymentStatus::Refunded->value, PaymentStatus::PartiallyRefunded->value])
            ->whereNotNull('reservation_expires_at')
            ->where('reservation_expires_at', '<=', $this->clock->now())
            ->orderBy('id')
            ->limit($chunkSize)
            ->pluck('id');

        foreach ($ids as $id) {
            $didExpire = DB::transaction(function () use ($id): bool {
                $order = Order::query()->whereKey($id)->lockForUpdate()->first();
                if ($order === null) {
                    return false;
                }

                $before = $order->status;
                $this->lifecycle->expireIfDue($order);

                return $order->status !== $before && $order->status === OrderStatus::Expired;
            });

            if ($didExpire) {
                $expired++;
            } else {
                $skipped++;
            }
        }

        $this->logger->info('expiration_command', [
            'expired' => $expired,
            'skipped' => $skipped,
            'chunk' => $chunkSize,
        ]);

        return ['expired' => $expired, 'skipped' => $skipped];
    }
}
