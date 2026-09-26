<?php

declare(strict_types=1);

namespace App\Domains\Geography\Enums;

enum SpatialValidationStatus: string
{
    case Pending = 'pending';
    case Valid = 'valid';
    case Warning = 'warning';
    case Invalid = 'invalid';
}
