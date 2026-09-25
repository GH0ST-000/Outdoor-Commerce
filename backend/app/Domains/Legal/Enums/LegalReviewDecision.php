<?php

declare(strict_types=1);

namespace App\Domains\Legal\Enums;

enum LegalReviewDecision: string
{
    case Approved = 'approved';
    case ChangesRequested = 'changes_requested';
    case Rejected = 'rejected';
}
