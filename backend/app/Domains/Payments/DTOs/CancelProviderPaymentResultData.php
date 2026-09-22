<?php

declare(strict_types=1);

namespace App\Domains\Payments\DTOs;

use App\Domains\Payments\Enums\PaymentAttemptStatus;

final readonly class CancelProviderPaymentResultData
{
    public function __construct(
        public PaymentAttemptStatus $normalizedStatus,
        public string $providerStatus,
        public bool $cancelled,
    ) {}
}
