<?php

declare(strict_types=1);

namespace App\Domains\Payments\Providers\BankOfGeorgia;

use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Enums\PaymentNormalizedEventType;

/**
 * Official Payment Manager order_status.key values reviewed 2026-09-23:
 * https://api.bog.ge/docs/en/payments/standard-process/get-payment-details
 *
 * Labels (order_status.value) are ignored. Unknown keys never become success.
 */
final class BankOfGeorgiaStatusMapper
{
    public function toNormalizedStatus(string $providerKey): PaymentAttemptStatus
    {
        return match ($providerKey) {
            'created' => PaymentAttemptStatus::RequiresAction,
            'processing' => PaymentAttemptStatus::Processing,
            'completed' => PaymentAttemptStatus::Succeeded,
            'rejected' => PaymentAttemptStatus::Failed,
            'refund_requested',
            'refunded',
            'refunded_partially',
            'auth_requested',
            'blocked',
            'partial_completed' => PaymentAttemptStatus::ManualReview,
            default => PaymentAttemptStatus::Unknown,
        };
    }

    public function toEventType(string $providerKey): PaymentNormalizedEventType
    {
        return match ($providerKey) {
            'created' => PaymentNormalizedEventType::Created,
            'processing' => PaymentNormalizedEventType::Processing,
            'completed' => PaymentNormalizedEventType::Succeeded,
            'rejected' => PaymentNormalizedEventType::Failed,
            default => PaymentNormalizedEventType::Unknown,
        };
    }
}
