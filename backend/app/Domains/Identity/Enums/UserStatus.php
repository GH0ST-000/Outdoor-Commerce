<?php

declare(strict_types=1);

namespace App\Domains\Identity\Enums;

/**
 * Lifecycle state of an account. Only active accounts may authenticate.
 */
enum UserStatus: string
{
    case Active = 'active';

    case Disabled = 'disabled';
}
