<?php

declare(strict_types=1);

namespace App\Domains\Legal\Enums;

enum SeasonScheduleType: string
{
    case FixedRange = 'fixed_range';
    case AnnualRecurring = 'annual_recurring';
}
