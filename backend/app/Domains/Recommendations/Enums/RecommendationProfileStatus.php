<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Enums;

enum RecommendationProfileStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Published = 'published';
    case Superseded = 'superseded';
    case Archived = 'archived';
}
