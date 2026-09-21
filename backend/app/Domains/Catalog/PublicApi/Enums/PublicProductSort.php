<?php

declare(strict_types=1);

namespace App\Domains\Catalog\PublicApi\Enums;

enum PublicProductSort: string
{
    case Default = 'default';
    case Featured = 'featured';
    case Newest = 'newest';
    case PriceAsc = 'price_asc';
    case PriceDesc = 'price_desc';
    case NameAsc = 'name_asc';
    case NameDesc = 'name_desc';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $sort): string => $sort->value, self::cases());
    }
}
