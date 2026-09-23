<?php

declare(strict_types=1);

namespace App\Domains\Payments\Enums;

enum PaymentNormalizedEventType: string
{
    case Created = 'created';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Unknown = 'unknown';
}
