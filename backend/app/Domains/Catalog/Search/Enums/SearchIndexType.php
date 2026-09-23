<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Search\Enums;

enum SearchIndexType: string
{
    case Variants = 'catalog_variants';
    case Categories = 'categories';
    case Brands = 'brands';
}
