<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Enums;

enum AttributeValueStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';

    public function isUsableInActiveVariant(): bool
    {
        return $this === self::Active;
    }

    public function isSelectableForNewVariant(): bool
    {
        return $this === self::Active;
    }
}
