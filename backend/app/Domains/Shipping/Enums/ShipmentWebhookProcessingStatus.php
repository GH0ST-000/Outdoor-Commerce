<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Enums;

enum ShipmentWebhookProcessingStatus: string
{
    case Received = 'received';
    case Verified = 'verified';
    case Processed = 'processed';
    case Ignored = 'ignored';
    case Failed = 'failed';
    case Unmatched = 'unmatched';
}
