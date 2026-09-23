<?php

declare(strict_types=1);

namespace App\Domains\Payments\DTOs;

use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Enums\PaymentNormalizedEventType;
use Carbon\CarbonImmutable;

final readonly class VerifiedProviderWebhookData
{
    /**
     * @param  array<string, scalar|null>  $safeMetadata
     */
    public function __construct(
        public string $providerEventId,
        public string $providerPaymentId,
        public ?string $providerTransactionId,
        public PaymentNormalizedEventType $eventType,
        public PaymentAttemptStatus $normalizedStatus,
        public string $providerStatus,
        public ?int $amountMinor,
        public ?string $currency,
        public ?CarbonImmutable $occurredAt,
        public array $safeMetadata,
        public bool $signatureVerified,
    ) {}
}
