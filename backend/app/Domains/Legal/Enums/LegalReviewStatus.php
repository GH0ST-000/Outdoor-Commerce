<?php

declare(strict_types=1);

namespace App\Domains\Legal\Enums;

enum LegalReviewStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Superseded = 'superseded';
    case Archived = 'archived';
}
