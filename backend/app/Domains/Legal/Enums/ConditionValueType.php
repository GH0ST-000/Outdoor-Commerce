<?php

declare(strict_types=1);

namespace App\Domains\Legal\Enums;

enum ConditionValueType: string
{
    case String = 'string';
    case Integer = 'integer';
    case Decimal = 'decimal';
    case Boolean = 'boolean';
    case Date = 'date';
    case Time = 'time';
    case Reference = 'reference';
}
