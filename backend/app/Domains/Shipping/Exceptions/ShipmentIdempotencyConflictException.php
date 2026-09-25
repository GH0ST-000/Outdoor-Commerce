<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

final class ShipmentIdempotencyConflictException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'This shipment request conflicts with a previous idempotency key.',
            'SHIPMENT_IDEMPOTENCY_CONFLICT',
        );
    }
}
