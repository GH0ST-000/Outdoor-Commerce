<?php

declare(strict_types=1);

namespace App\Domains\Payments\Services;

use App\Domains\Orders\Actions\AssertOrderAccessAction;
use App\Domains\Orders\DTOs\OrderActorData;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Exceptions\PaymentException;
use App\Domains\Payments\Models\PaymentAttempt;

final class GetPaymentAttemptService
{
    public function __construct(private readonly AssertOrderAccessAction $assertAccess) {}

    public function execute(OrderActorData $actor, string $attemptPublicId): PaymentAttempt
    {
        $attempt = PaymentAttempt::query()->where('public_id', $attemptPublicId)->first();
        if ($attempt === null) {
            throw PaymentException::notFound();
        }
        $order = Order::query()->whereKey($attempt->order_id)->first();
        if ($order === null) {
            throw PaymentException::notFound();
        }
        $this->assertAccess->execute($order, $actor);

        return $attempt;
    }

    public function current(OrderActorData $actor, string $orderPublicId): ?PaymentAttempt
    {
        $order = Order::query()->where('public_id', $orderPublicId)->first();
        if ($order === null) {
            throw PaymentException::orderNotFound();
        }
        $this->assertAccess->execute($order, $actor);

        return PaymentAttempt::query()
            ->where('order_id', $order->id)
            ->orderByDesc('id')
            ->first();
    }

    public function currentForOrder(Order $order): ?PaymentAttempt
    {
        return PaymentAttempt::query()
            ->where('order_id', $order->id)
            ->orderByDesc('id')
            ->first();
    }
}
