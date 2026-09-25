<?php

declare(strict_types=1);

namespace App\Domains\Payments\DTOs;

use Carbon\CarbonImmutable;

final readonly class CreateProviderPaymentRequestData
{
    /**
     * @param  array<string, scalar|null>  $metadata
     * @param  list<ProviderPaymentBasketItemData>  $basketItems
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
        public array $basketItems = [],
        public int $deliveryAmountMinor = 0,
        public int $discountTotalMinor = 0,
        public ?CarbonImmutable $reservationExpiresAt = null,
        public string $providerIdempotencyKey = '',
        public ?string $failureReturnUrl = null,
    ) {}
}
