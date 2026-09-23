<?php

declare(strict_types=1);

namespace App\Domains\Payments\DTOs;

final readonly class CreateProviderPaymentRequestData
{
    /**
     * @param  array<string, scalar|null>  $metadata
     */
    public function __construct(
        public string $merchantReference,
        public int $amountMinor,
        public string $currency,
        public string $description,
        public string $returnUrl,
        public string $callbackUrl,
        public string $customerLocale,
        public ?string $customerEmail,
        public ?string $customerPhone,
        public array $metadata,
    ) {}
}
