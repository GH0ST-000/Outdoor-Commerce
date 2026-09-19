<?php

declare(strict_types=1);

namespace App\Domains\Identity\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

final class LastActiveAdminException extends DomainException
{
    public static function cannotDisable(): self
    {
        return new self(
            'At least one active administrator must remain.',
            'LAST_ACTIVE_ADMIN_REQUIRED',
        );
    }

    public static function cannotRemoveRole(): self
    {
        return new self(
            'At least one active administrator must remain.',
            'LAST_ACTIVE_ADMIN_REQUIRED',
        );
    }
}
