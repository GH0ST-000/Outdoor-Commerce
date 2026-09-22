<?php

declare(strict_types=1);

namespace App\Domains\Payments\Actions;

use App\Domains\Orders\DTOs\OrderActorData;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Models\PaymentAttempt;
use App\Domains\Payments\Services\GetPaymentAttemptService;

final class GetPaymentAttemptAction
{
    public function __construct(private readonly GetPaymentAttemptService $payments) {}

    public function execute(OrderActorData $actor, string $attemptPublicId): PaymentAttempt
    {
        return $this->payments->execute($actor, $attemptPublicId);
    }

    public function current(OrderActorData $actor, string $orderPublicId): ?PaymentAttempt
    {
        return $this->payments->current($actor, $orderPublicId);
    }

    public function currentForOrder(Order $order): ?PaymentAttempt
    {
        return $this->payments->currentForOrder($order);
    }
}
