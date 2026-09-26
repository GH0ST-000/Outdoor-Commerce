<?php

declare(strict_types=1);

namespace App\Domains\Geography\Enums;

enum SpatialVerificationStatus: string
{
    case Unverified = 'unverified';
    case PendingReview = 'pending_review';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
}
