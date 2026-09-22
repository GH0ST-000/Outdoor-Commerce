<?php

declare(strict_types=1);

namespace App\Domains\Orders\Enums;

enum OrderAdjustmentType: string
{
    case Promotion = 'promotion';
    case Delivery = 'delivery';
    case Tax = 'tax';
    case Rounding = 'rounding';
    case Manual = 'manual';
}
