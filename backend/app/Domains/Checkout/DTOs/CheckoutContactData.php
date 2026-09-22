<?php

declare(strict_types=1);

namespace App\Domains\Checkout\DTOs;

final readonly class CheckoutContactData
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $phone,
        public ?string $customerNote = null,
    ) {}
}
