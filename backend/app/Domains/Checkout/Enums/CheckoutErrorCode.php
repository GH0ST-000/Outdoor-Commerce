<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Enums;

enum CheckoutErrorCode: string
{
    case CartEmpty = 'CHECKOUT_CART_EMPTY';
    case CartChanged = 'CHECKOUT_CART_CHANGED';
    case VersionConflict = 'CHECKOUT_VERSION_CONFLICT';
    case ContactIncomplete = 'CHECKOUT_CONTACT_INCOMPLETE';
    case AddressIncomplete = 'CHECKOUT_ADDRESS_INCOMPLETE';
    case FulfillmentRequired = 'CHECKOUT_FULFILLMENT_REQUIRED';
    case FulfillmentUnavailable = 'CHECKOUT_FULFILLMENT_UNAVAILABLE';
    case DeliveryZoneUnavailable = 'CHECKOUT_DELIVERY_ZONE_UNAVAILABLE';
    case ProductUnavailable = 'CHECKOUT_PRODUCT_UNAVAILABLE';
    case VariantUnavailable = 'CHECKOUT_VARIANT_UNAVAILABLE';
    case PriceChanged = 'CHECKOUT_PRICE_CHANGED';
    case PromotionChanged = 'CHECKOUT_PROMOTION_CHANGED';
    case InsufficientStock = 'CHECKOUT_INSUFFICIENT_STOCK';
    case QuantityLimitExceeded = 'CHECKOUT_QUANTITY_LIMIT_EXCEEDED';
    case RestrictionBlocked = 'CHECKOUT_RESTRICTION_BLOCKED';
    case QuoteExpired = 'CHECKOUT_QUOTE_EXPIRED';
    case SessionExpired = 'CHECKOUT_SESSION_EXPIRED';
    case QuoteSuperseded = 'CHECKOUT_QUOTE_SUPERSEDED';
    case CurrencyMismatch = 'CHECKOUT_CURRENCY_MISMATCH';
    case IdempotencyConflict = 'CHECKOUT_IDEMPOTENCY_CONFLICT';
    case SessionNotFound = 'CHECKOUT_SESSION_NOT_FOUND';
    case SessionNotMutable = 'CHECKOUT_SESSION_NOT_MUTABLE';
    case RefreshLimit = 'CHECKOUT_REFRESH_LIMIT';
}
