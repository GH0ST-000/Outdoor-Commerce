<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Enums;

enum CheckoutQuoteStatus: string
{
    case Active = 'active';
    case Superseded = 'superseded';
    case Expired = 'expired';
    case Consumed = 'consumed';
    case Cancelled = 'cancelled';

    public function isConsumable(): bool
    {
        return $this === self::Active;
    }
}
