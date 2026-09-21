<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

final class PublicCatalogNotFoundException extends DomainException
{
    public static function product(): self
    {
        return new self('The requested product was not found.', 'CATALOG_PRODUCT_NOT_FOUND');
    }

    public static function category(): self
    {
        return new self('The requested category was not found.', 'CATALOG_CATEGORY_NOT_FOUND');
    }

    public static function brand(): self
    {
        return new self('The requested brand was not found.', 'CATALOG_BRAND_NOT_FOUND');
    }
}
