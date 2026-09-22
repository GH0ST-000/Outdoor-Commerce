<?php

declare(strict_types=1);

namespace App\Domains\Payments\Actions;

use App\Domains\Orders\DTOs\OrderActorData;
use App\Domains\Payments\DTOs\CreatePaymentAttemptData;
use App\Domains\Payments\Models\PaymentAttempt;
use App\Domains\Payments\Services\CreatePaymentAttemptService;

final class CreatePaymentAttemptAction
{
    public function __construct(private readonly CreatePaymentAttemptService $payments) {}

    public function execute(OrderActorData $actor, CreatePaymentAttemptData $data): PaymentAttempt
    {
        return $this->payments->execute($actor, $data);
    }
}
