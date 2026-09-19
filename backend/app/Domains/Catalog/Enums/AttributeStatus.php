<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Enums;

enum AttributeStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';

    public function isAssignable(): bool
    {
        return $this === self::Active;
    }
}
