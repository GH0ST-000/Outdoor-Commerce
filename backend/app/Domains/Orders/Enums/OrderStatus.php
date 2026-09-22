<?php

declare(strict_types=1);

namespace App\Domains\Orders\Enums;

enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case PaymentProcessing = 'payment_processing';
    case Confirmed = 'confirmed';
    case ManualReview = 'manual_review';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Cancelled, self::Expired, self::Confirmed => true,
            default => false,
        };
    }

    public function allowsCustomerCancellation(): bool
    {
        return $this === self::PendingPayment;
    }
}
