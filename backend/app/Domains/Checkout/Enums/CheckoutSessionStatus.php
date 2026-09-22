<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Enums;

enum CheckoutSessionStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Quoted = 'quoted';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Converted = 'converted';

    public function isMutable(): bool
    {
        return match ($this) {
            self::Draft, self::Ready, self::Quoted => true,
            default => false,
        };
    }

    public function isActive(): bool
    {
        return $this->isMutable();
    }
}
