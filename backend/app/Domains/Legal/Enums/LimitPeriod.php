<?php

declare(strict_types=1);

namespace App\Domains\Legal\Enums;

enum LimitPeriod: string
{
    case Event = 'event';
    case Day = 'day';
    case Week = 'week';
    case Season = 'season';
    case Year = 'year';
    case Lifetime = 'lifetime';
    case NotApplicable = 'not_applicable';
}
