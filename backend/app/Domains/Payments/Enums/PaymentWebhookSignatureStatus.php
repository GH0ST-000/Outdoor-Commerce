<?php

declare(strict_types=1);

namespace App\Domains\Payments\Enums;

enum PaymentWebhookSignatureStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Invalid = 'invalid';
    case Missing = 'missing';
}
