<?php

declare(strict_types=1);

namespace App\Domains\Shared\Exceptions;

/**
 * Base type for domain exceptions. Prefer specific subclasses in business modules.
 */
class DomainException extends \Exception
{
    public function __construct(
        string $message,
        private readonly string $errorCode = 'DOMAIN_ERROR',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
