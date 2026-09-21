<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Carries a stable failure code plus an operator-safe message. The underlying
 * technical detail stays in the exception message / log and is never persisted
 * on the asset or returned by the API.
 */
class MediaProcessingException extends RuntimeException
{
    public function __construct(
        public readonly string $failureCode,
        public readonly string $safeMessage,
        string $internalMessage = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($internalMessage !== '' ? $internalMessage : $safeMessage, 0, $previous);
    }
}
