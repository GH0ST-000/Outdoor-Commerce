<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Enums;

enum PromotionTargetType: string
{
    case AllProducts = 'all_products';
    case Product = 'product';
    case ProductVariant = 'product_variant';
    case Category = 'category';
    case Brand = 'brand';
}
