<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

final class PublicCatalogQueryException extends DomainException
{
    public static function unsupportedLocale(string $locale): self
    {
        return new self(
            'The requested locale is not supported.',
            'CATALOG_LOCALE_UNSUPPORTED',
        );
    }

    public static function unsupportedCurrency(string $currency): self
    {
        unset($currency);

        return new self(
            'The requested currency is not supported for the public catalog.',
            'CATALOG_CURRENCY_UNSUPPORTED',
        );
    }

    public static function invalidSort(): self
    {
        return new self('The requested sort is not allowed.', 'CATALOG_SORT_INVALID');
    }

    public static function invalidFilter(string $message = 'One or more catalog filters are invalid.'): self
    {
        return new self($message, 'CATALOG_FILTER_INVALID');
    }

    public static function queryTooLarge(): self
    {
        return new self('The catalog query is too large.', 'CATALOG_QUERY_TOO_LARGE');
    }
}
