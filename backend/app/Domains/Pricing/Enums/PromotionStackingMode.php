<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Enums;

enum PromotionStackingMode: string
{
    case Exclusive = 'exclusive';
    case Combinable = 'combinable';
}
