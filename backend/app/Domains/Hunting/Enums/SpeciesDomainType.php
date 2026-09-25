<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Enums;

enum SpeciesDomainType: string
{
    case Terrestrial = 'terrestrial';
    case Freshwater = 'freshwater';
    case Marine = 'marine';
    case Migratory = 'migratory';
    case Mixed = 'mixed';
}
