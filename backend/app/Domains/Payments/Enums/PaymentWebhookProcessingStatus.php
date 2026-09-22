<?php

declare(strict_types=1);

namespace App\Domains\Payments\Enums;

enum PaymentWebhookProcessingStatus: string
{
    case Received = 'received';
    case Verified = 'verified';
    case Processing = 'processing';
    case Processed = 'processed';
    case Ignored = 'ignored';
    case Failed = 'failed';
    case ManualReview = 'manual_review';
    case Unmatched = 'unmatched';
}
