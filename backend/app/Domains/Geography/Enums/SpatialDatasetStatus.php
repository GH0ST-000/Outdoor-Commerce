<?php

declare(strict_types=1);

namespace App\Domains\Geography\Enums;

enum SpatialDatasetStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Superseded = 'superseded';
    case Archived = 'archived';
}
