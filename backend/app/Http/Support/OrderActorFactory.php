<?php

declare(strict_types=1);

namespace App\Http\Support;

use App\Domains\Checkout\DTOs\CheckoutActorData;
use App\Domains\Orders\DTOs\OrderActorData;
use Illuminate\Http\Request;

final class OrderActorFactory
{
    public function __construct(
        private readonly CheckoutActorFactory $checkout,
        private readonly GuestOrderCookie $orderCookie,
    ) {}

    public function fromRequest(
        Request $request,
        ?string $idempotencyKey = null,
        ?string $endpoint = null,
        ?int $expectedSessionVersion = null,
    ): OrderActorData {
        $checkoutActor = $this->checkout->fromRequest(
            $request,
            $idempotencyKey,
            $endpoint,
            $expectedSessionVersion,
        );

        return new OrderActorData(
            checkout: $checkoutActor,
            rawOrderToken: $this->orderCookie->rawToken($request),
            idempotencyKey: $idempotencyKey,
            endpoint: $endpoint,
        );
    }

    public function checkoutActor(OrderActorData $actor): CheckoutActorData
    {
        return $actor->checkout;
    }
}
