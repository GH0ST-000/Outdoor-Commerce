<?php

declare(strict_types=1);

namespace App\Domains\Payments\DTOs;

final readonly class ProviderPaymentReferenceData
{
    public function __construct(
        public string $merchantReference,
        public ?string $providerPaymentId,
        public ?string $providerTransactionId,
    ) {}
}
