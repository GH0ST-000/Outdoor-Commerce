<?php

declare(strict_types=1);

namespace App\Domains\Geography\Enums;

enum SpatialGeometryVersionStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Published = 'published';
    case Rejected = 'rejected';
    case Superseded = 'superseded';
    case Archived = 'archived';
}
