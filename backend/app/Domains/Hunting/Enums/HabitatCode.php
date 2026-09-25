<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Enums;

/**
 * Controlled habitat vocabulary identifiers. These are ecological classes,
 * not hunting zones and not a claim that a place is legally huntable.
 */
enum HabitatCode: string
{
    case Forest = 'forest';
    case Alpine = 'alpine';
    case Wetland = 'wetland';
    case Grassland = 'grassland';
    case AgriculturalLand = 'agricultural_land';
    case River = 'river';
    case Stream = 'stream';
    case Lake = 'lake';
    case Reservoir = 'reservoir';
    case Coastal = 'coastal';
    case Marine = 'marine';
    case Rocky = 'rocky';
    case Mixed = 'mixed';
}
