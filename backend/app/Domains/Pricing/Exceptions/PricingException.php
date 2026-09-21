<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

/**
 * Base for pricing domain errors (HTTP 422 via DomainException handler unless subclassed).
 */
abstract class PricingException extends DomainException
{
    public function __construct(
        string $message,
        string $errorCode = 'PRICING_ERROR',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $errorCode, $code, $previous);
    }
}
