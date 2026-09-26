<?php

declare(strict_types=1);

namespace App\Domains\Geography\Enums;

enum SpatialDetailLevel: string
{
    case Country = 'country';
    case Region = 'region';
    case Local = 'local';
    case Full = 'full';
}
