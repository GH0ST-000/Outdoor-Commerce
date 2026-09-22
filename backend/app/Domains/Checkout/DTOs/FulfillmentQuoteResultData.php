<?php

declare(strict_types=1);

namespace App\Domains\Checkout\DTOs;

final readonly class FulfillmentQuoteResultData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public bool $eligible,
        public ?int $amountMinor,
        public string $currency,
        public ?int $estimatedMinDays,
        public ?int $estimatedMaxDays,
        public ?string $unavailableReason,
        public array $metadata = [],
    ) {}
}
