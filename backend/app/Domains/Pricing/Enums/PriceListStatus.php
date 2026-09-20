<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Enums;

enum PriceListStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';

    public function suppliesEffectivePrices(): bool
    {
        return $this === self::Active;
    }
}
