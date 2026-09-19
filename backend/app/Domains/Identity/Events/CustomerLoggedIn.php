<?php

declare(strict_types=1);

namespace App\Domains\Identity\Events;

/**
 * A customer successfully authenticated. Carries the identifier only.
 */
final readonly class CustomerLoggedIn
{
    public function __construct(
        public int $userId,
    ) {}
}
