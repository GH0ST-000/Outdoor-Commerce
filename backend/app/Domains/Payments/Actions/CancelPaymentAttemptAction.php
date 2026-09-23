<?php

declare(strict_types=1);

namespace App\Domains\Payments\Actions;

use App\Domains\Orders\DTOs\OrderActorData;
use App\Domains\Payments\Models\PaymentAttempt;
use App\Domains\Payments\Services\CancelPaymentAttemptService;

final class CancelPaymentAttemptAction
{
    public function __construct(private readonly CancelPaymentAttemptService $payments) {}

    public function execute(OrderActorData $actor, string $attemptPublicId): PaymentAttempt
    {
        return $this->payments->execute($actor, $attemptPublicId);
    }
}
