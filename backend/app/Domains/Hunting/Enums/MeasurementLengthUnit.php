<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Enums;

enum MeasurementLengthUnit: string
{
    case Millimetre = 'mm';
    case Centimetre = 'cm';
    case Metre = 'm';
}
