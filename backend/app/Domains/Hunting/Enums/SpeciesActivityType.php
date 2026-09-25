<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Enums;

/**
 * Content-navigation classification only. Never a legal hunting or fishing permission.
 */
enum SpeciesActivityType: string
{
    case Hunting = 'hunting';
    case Fishing = 'fishing';
    case Wildlife = 'wildlife';
    case HuntingAndWildlife = 'hunting_and_wildlife';
    case FishingAndWildlife = 'fishing_and_wildlife';
}
