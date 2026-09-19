<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Enums;

enum AttributeType: string
{
    case Select = 'select';
    case Color = 'color';

    public function allowsColorHex(): bool
    {
        return $this === self::Color;
    }
}
