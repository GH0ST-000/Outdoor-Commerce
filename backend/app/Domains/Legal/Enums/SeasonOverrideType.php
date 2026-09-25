<?php

declare(strict_types=1);

namespace App\Domains\Legal\Enums;

enum SeasonOverrideType: string
{
    case Closure = 'closure';
    case SpecialOpening = 'special_opening';
    case DateAdjustment = 'date_adjustment';
    case LimitAdjustment = 'limit_adjustment';
    case ConditionAdjustment = 'condition_adjustment';
}
