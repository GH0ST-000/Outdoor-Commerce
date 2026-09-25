<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Enums;

enum ShipmentWebhookSignatureStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Invalid = 'invalid';
    case Missing = 'missing';
}
