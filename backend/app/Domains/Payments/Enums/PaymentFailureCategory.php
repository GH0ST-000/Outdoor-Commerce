<?php

declare(strict_types=1);

namespace App\Domains\Payments\Enums;

enum PaymentFailureCategory: string
{
    case Declined = 'declined';
    case CancelledByCustomer = 'cancelled_by_customer';
    case Expired = 'expired';
    case ProviderUnavailable = 'provider_unavailable';
    case ValidationFailed = 'validation_failed';
    case Unknown = 'unknown';
}
