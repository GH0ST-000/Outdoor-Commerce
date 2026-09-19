<?php

declare(strict_types=1);

namespace App\Domains\Identity\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

/**
 * Raised when a credential pair cannot be turned into a session.
 *
 * The message and error code are deliberately generic so that callers cannot
 * distinguish "unknown email" from "wrong password".
 */
final class AuthenticationFailedException extends DomainException
{
    public const ERROR_CODE = 'INVALID_CREDENTIALS';

    public const PUBLIC_MESSAGE = 'These credentials do not match our records.';

    public static function invalidCredentials(): self
    {
        return new self(self::PUBLIC_MESSAGE, self::ERROR_CODE, 401);
    }
}
