<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Enums;

enum ProductVariantStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';

    public function canBeDefault(): bool
    {
        return $this !== self::Archived;
    }
}
