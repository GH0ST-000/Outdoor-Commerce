<?php

declare(strict_types=1);

namespace App\Domains\Identity\Events;

/**
 * A customer confirmed ownership of their email address. Carries the identifier only.
 */
final readonly class CustomerEmailVerified
{
    public function __construct(
        public int $userId,
    ) {}
}
