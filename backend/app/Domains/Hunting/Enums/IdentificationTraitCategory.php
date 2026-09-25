<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Enums;

enum IdentificationTraitCategory: string
{
    case Size = 'size';
    case Color = 'color';
    case Markings = 'markings';
    case Shape = 'shape';
    case Sound = 'sound';
    case Tracks = 'tracks';
    case Flight = 'flight';
    case Fins = 'fins';
    case Scales = 'scales';
    case SeasonalPlumage = 'seasonal_plumage';
    case SexDifference = 'sex_difference';
    case JuvenileDifference = 'juvenile_difference';
}
