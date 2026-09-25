<?php

declare(strict_types=1);

namespace App\Domains\Legal\Enums;

enum AvailabilityMode: string
{
    case AnyDate = 'any_date';
    case EntirePeriod = 'entire_period';
    case Timeline = 'timeline';
}
