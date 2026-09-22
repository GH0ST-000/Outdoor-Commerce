<?php

declare(strict_types=1);

namespace App\Domains\Cart\Enums;

enum CartIssueCode: string
{
    case ProductUnavailable = 'PRODUCT_UNAVAILABLE';
    case VariantUnavailable = 'VARIANT_UNAVAILABLE';
    case InsufficientStock = 'INSUFFICIENT_STOCK';
    case QuantityLimit = 'QUANTITY_LIMIT';
    case QuantityAdjustedOnMerge = 'QUANTITY_ADJUSTED_ON_MERGE';
    case PriceChanged = 'PRICE_CHANGED';
    case PriceIncreased = 'PRICE_INCREASED';
    case PriceDecreased = 'PRICE_DECREASED';
    case PromotionEnded = 'PROMOTION_ENDED';
    case PromotionApplied = 'PROMOTION_APPLIED';
    case CartExpired = 'CART_EXPIRED';
}
