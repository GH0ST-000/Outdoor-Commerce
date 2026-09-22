<?php

declare(strict_types=1);

namespace App\Domains\Payments\Actions;

use App\Domains\Orders\DTOs\OrderActorData;
use App\Domains\Payments\Models\PaymentAttempt;
use App\Domains\Payments\Services\SimulateTestPaymentService;

final class SimulateTestPaymentAction
{
    public function __construct(private readonly SimulateTestPaymentService $payments) {}

    public function execute(OrderActorData $actor, string $attemptPublicId, string $outcome): PaymentAttempt
    {
        return $this->payments->execute($actor, $attemptPublicId, $outcome);
    }
}
