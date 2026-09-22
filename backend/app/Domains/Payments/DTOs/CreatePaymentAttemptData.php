<?php

declare(strict_types=1);

namespace App\Domains\Payments\DTOs;

final readonly class CreatePaymentAttemptData
{
    public function __construct(
        public string $orderPublicId,
        public string $paymentMethodCode,
        public ?int $orderVersion,
    ) {}
}
