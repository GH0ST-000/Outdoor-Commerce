<?php

declare(strict_types=1);

namespace App\Domains\Geography\Enums;

enum SpatialReviewStatus: string
{
    case Draft = 'draft';
    case Validating = 'validating';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Published = 'published';
    case Rejected = 'rejected';
    case Superseded = 'superseded';
    case Archived = 'archived';
}
