<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

final class VariantInactiveException extends DomainException
{
    public function __construct(string $message = 'Product variant is not active for inventory operations.')
    {
        parent::__construct($message, 'VARIANT_INACTIVE');
    }
}
