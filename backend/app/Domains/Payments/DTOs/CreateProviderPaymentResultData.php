<?php

declare(strict_types=1);

namespace App\Domains\Payments\DTOs;

use App\Domains\Payments\Enums\PaymentActionType;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use Carbon\CarbonImmutable;

final readonly class CreateProviderPaymentResultData
{
    /**
     * @param  array<string, scalar|null>  $safeMetadata
     */
    public function __construct(
        public string $providerPaymentId,
        public ?string $providerTransactionId,
        public PaymentAttemptStatus $normalizedStatus,
        public string $providerStatus,
        public PaymentActionType $action,
        public ?string $redirectUrl,
        public ?CarbonImmutable $expiresAt,
        public array $safeMetadata = [],
    ) {}
}
