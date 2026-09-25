<?php

declare(strict_types=1);

namespace App\Domains\Legal\Enums;

enum LegalLimitType: string
{
    case Bag = 'bag';
    case Catch = 'catch';
    case Possession = 'possession';
    case Size = 'size';
    case Weight = 'weight';
    case Quantity = 'quantity';
    case Duration = 'duration';
    case Other = 'other';
}
