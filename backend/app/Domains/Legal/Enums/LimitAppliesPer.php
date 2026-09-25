<?php

declare(strict_types=1);

namespace App\Domains\Legal\Enums;

enum LimitAppliesPer: string
{
    case Person = 'person';
    case Permit = 'permit';
    case Group = 'group';
    case Vessel = 'vessel';
    case Location = 'location';
    case Other = 'other';
}
