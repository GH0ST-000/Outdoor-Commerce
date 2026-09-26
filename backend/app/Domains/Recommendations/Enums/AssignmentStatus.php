<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Enums;

enum AssignmentStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';
}
