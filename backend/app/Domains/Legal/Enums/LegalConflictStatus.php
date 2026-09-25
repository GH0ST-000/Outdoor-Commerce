<?php

declare(strict_types=1);

namespace App\Domains\Legal\Enums;

enum LegalConflictStatus: string
{
    case Open = 'open';
    case UnderReview = 'under_review';
    case Resolved = 'resolved';
    case Accepted = 'accepted';
    case FalsePositive = 'false_positive';
}
