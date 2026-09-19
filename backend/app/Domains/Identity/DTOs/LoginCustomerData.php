<?php

declare(strict_types=1);

namespace App\Domains\Identity\DTOs;

use SensitiveParameter;

final readonly class LoginCustomerData
{
    public function __construct(
        public string $email,
        #[SensitiveParameter]
        public string $password,
        public bool $remember = false,
    ) {}
}
