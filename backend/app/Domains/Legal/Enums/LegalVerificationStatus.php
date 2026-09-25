<?php

declare(strict_types=1);

namespace App\Domains\Legal\Enums;

enum LegalVerificationStatus: string
{
    case Unverified = 'unverified';
    case PendingReview = 'pending_review';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
}
