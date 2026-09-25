<?php

declare(strict_types=1);

namespace App\Domains\Legal\Enums;

enum SeasonBoundaryPrecision: string
{
    case Date = 'date';
    case DateTime = 'datetime';
}
