<?php

declare(strict_types=1);

namespace App\Domains\Orders\Enums;

enum OrderStatusReasonCode: string
{
    case OrderCreated = 'order_created';
    case CustomerCancelled = 'customer_cancelled';
    case UnpaidExpired = 'unpaid_expired';
    case PaymentStarted = 'payment_started';
    case PaymentReturnedToPending = 'payment_returned_to_pending';
    case PaymentConfirmed = 'payment_confirmed';
    case ManualReviewResolved = 'manual_review_resolved';
    case InvalidTransition = 'invalid_transition';
    case PaymentSucceededAfterExpiration = 'payment_succeeded_after_expiration';
    case PaymentSucceededAfterCancellation = 'payment_succeeded_after_cancellation';
    case PaymentAmountMismatch = 'payment_amount_mismatch';
    case PaymentCurrencyMismatch = 'payment_currency_mismatch';
    case InventoryCommitFailed = 'inventory_commit_failed';
}
