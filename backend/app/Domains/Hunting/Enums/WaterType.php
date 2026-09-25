<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Enums;

enum WaterType: string
{
    case Freshwater = 'freshwater';
    case Brackish = 'brackish';
    case Marine = 'marine';
    case Mixed = 'mixed';
}
