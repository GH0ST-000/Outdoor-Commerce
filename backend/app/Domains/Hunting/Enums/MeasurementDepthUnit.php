<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Enums;

enum MeasurementDepthUnit: string
{
    case Metre = 'm';
    case Centimetre = 'cm';
}
