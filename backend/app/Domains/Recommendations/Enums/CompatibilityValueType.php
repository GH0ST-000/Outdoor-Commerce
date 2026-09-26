<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Enums;

enum CompatibilityValueType: string
{
    case String = 'string';
    case Integer = 'integer';
    case Decimal = 'decimal';
    case Boolean = 'boolean';
}
