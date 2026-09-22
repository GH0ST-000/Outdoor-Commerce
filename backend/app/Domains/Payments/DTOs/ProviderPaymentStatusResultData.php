<?php

declare(strict_types=1);

namespace App\Domains\Payments\DTOs;

use App\Domains\Payments\Enums\PaymentAttemptStatus;
use Carbon\CarbonImmutable;

final readonly class ProviderPaymentStatusResultData
{
    /**
     * @param  array<string, scalar|null>  $safeMetadata
     */
    public function __construct(
        public string $providerPaymentId,
        public ?string $providerTransactionId,
        public PaymentAttemptStatus $normalizedStatus,
        public string $providerStatus,
        public ?int $amountMinor,
        public ?string $currency,
        public ?CarbonImmutable $occurredAt,
        public array $safeMetadata = [],
    ) {}
}
