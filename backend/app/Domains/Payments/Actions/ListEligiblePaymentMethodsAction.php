<?php

declare(strict_types=1);

namespace App\Domains\Payments\Actions;

use App\Domains\Orders\Actions\AssertOrderAccessAction;
use App\Domains\Orders\DTOs\OrderActorData;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\DTOs\PaymentMethodData;
use App\Domains\Payments\Exceptions\PaymentException;
use App\Domains\Payments\Services\PaymentMethodRegistry;

final class ListEligiblePaymentMethodsAction
{
    public function __construct(
        private readonly AssertOrderAccessAction $assertAccess,
        private readonly PaymentMethodRegistry $methods,
    ) {}

    /**
     * @return list<PaymentMethodData>
     */
    public function execute(OrderActorData $actor, string $orderPublicId): array
    {
        $order = Order::query()->where('public_id', $orderPublicId)->first();
        if ($order === null) {
            throw PaymentException::orderNotFound();
        }
        $this->assertAccess->execute($order, $actor);

        return $this->methods->eligibleFor($order, $actor->locale());
    }
}
