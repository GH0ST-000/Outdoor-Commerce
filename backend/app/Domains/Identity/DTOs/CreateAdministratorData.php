<?php

declare(strict_types=1);

namespace App\Domains\Identity\DTOs;

final readonly class CreateAdministratorData
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $password,
        public bool $markEmailVerified = true,
    ) {}
}
