<?php

declare(strict_types=1);

namespace App\Domains\Cart\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

class CartException extends DomainException
{
    public static function productUnavailable(): self
    {
        return new self('This product is not available.', 'CART_PRODUCT_UNAVAILABLE');
    }

    public static function variantUnavailable(): self
    {
        return new self('This variant is not available.', 'CART_VARIANT_UNAVAILABLE');
    }

    public static function variantProductMismatch(): self
    {
        return new self('This variant does not belong to the requested product.', 'CART_VARIANT_UNAVAILABLE');
    }

    public static function insufficientStock(): self
    {
        return new self('Not enough stock is available for the requested quantity.', 'CART_INSUFFICIENT_STOCK');
    }

    public static function quantityLimitExceeded(): self
    {
        return new self('The requested quantity exceeds the allowed limit.', 'CART_QUANTITY_LIMIT_EXCEEDED');
    }

    public static function currencyMismatch(): self
    {
        return new self('The cart currency is not supported.', 'CART_CURRENCY_MISMATCH');
    }

    public static function notMutable(): self
    {
        return new self('This cart can no longer be changed.', 'CART_NOT_MUTABLE');
    }

    public static function itemNotFound(): self
    {
        return new self('Cart item was not found.', 'CART_ITEM_NOT_FOUND');
    }

    public static function tooManyLines(): self
    {
        return new self('The cart has too many unique items.', 'CART_QUANTITY_LIMIT_EXCEEDED');
    }
}
