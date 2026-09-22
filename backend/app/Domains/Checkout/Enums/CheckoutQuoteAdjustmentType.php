<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Enums;

enum CheckoutQuoteAdjustmentType: string
{
    case Promotion = 'promotion';
    case Delivery = 'delivery';
    case Tax = 'tax';
    case Rounding = 'rounding';
}
