<?php

declare(strict_types=1);

namespace App\Domains\Identity\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

final class InvalidRoleException extends DomainException
{
    public static function unknown(string $role): self
    {
        return new self(
            "The role [{$role}] is not an approved administrative role.",
            'INVALID_ROLE',
        );
    }
}
