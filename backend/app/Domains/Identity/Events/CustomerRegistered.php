<?php

declare(strict_types=1);

namespace App\Domains\Identity\Events;

/**
 * A customer account was created. Carries the identifier only — never credentials.
 */
final readonly class CustomerRegistered
{
    public function __construct(
        public int $userId,
    ) {}
}
