<?php

declare(strict_types=1);

namespace App\Domains\Geography\Enums;

enum SpatialImportStatus: string
{
    case Pending = 'pending';
    case Validating = 'validating';
    case Validated = 'validated';
    case Importing = 'importing';
    case Imported = 'imported';
    case PartiallyFailed = 'partially_failed';
    case Failed = 'failed';
}
