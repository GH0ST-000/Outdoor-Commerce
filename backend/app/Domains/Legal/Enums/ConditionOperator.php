<?php

declare(strict_types=1);

namespace App\Domains\Legal\Enums;

enum ConditionOperator: string
{
    case Equals = 'equals';
    case NotEquals = 'not_equals';
    case In = 'in';
    case NotIn = 'not_in';
    case GreaterThan = 'greater_than';
    case GreaterThanOrEqual = 'greater_than_or_equal';
    case LessThan = 'less_than';
    case LessThanOrEqual = 'less_than_or_equal';
    case Between = 'between';
    case Exists = 'exists';
    case NotExists = 'not_exists';
}
