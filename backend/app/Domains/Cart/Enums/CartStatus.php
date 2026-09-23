<?php

declare(strict_types=1);

namespace App\Domains\Cart\Enums;

enum CartStatus: string
{
    case Active = 'active';
    case Merged = 'merged';
    case Converted = 'converted';
    case Expired = 'expired';
    case Abandoned = 'abandoned';

    public function isMutable(): bool
    {
        return $this === self::Active;
    }
}
