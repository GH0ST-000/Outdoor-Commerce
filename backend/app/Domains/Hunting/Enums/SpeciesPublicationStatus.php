<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Enums;

enum SpeciesPublicationStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Published = 'published';
    case Archived = 'archived';

    public function isPublic(): bool
    {
        return $this === self::Published;
    }
}
