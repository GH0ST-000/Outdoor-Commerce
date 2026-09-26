<?php

declare(strict_types=1);

namespace App\Domains\Geography\Enums;

enum SpatialAssignmentStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Published = 'published';
    case Rejected = 'rejected';
    case Superseded = 'superseded';
    case Archived = 'archived';
}
