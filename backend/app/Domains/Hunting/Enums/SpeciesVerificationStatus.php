<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Enums;

enum SpeciesVerificationStatus: string
{
    case Unverified = 'unverified';
    case PartiallyVerified = 'partially_verified';
    case Verified = 'verified';
    case NeedsReview = 'needs_review';
}
