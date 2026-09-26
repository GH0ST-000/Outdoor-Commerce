<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Enums;

enum MerchandisingAdjustmentType: string
{
    case Boost = 'boost';
    case Demote = 'demote';
    case Pin = 'pin';
    case Exclude = 'exclude';
}
