<?php

declare(strict_types=1);

namespace App\Domains\Identity\DTOs;

use App\Domains\Identity\Support\EmailNormalizer;
use App\Domains\Identity\Support\NameNormalizer;
use SensitiveParameter;

/**
 * Validated input for creating a customer account.
 */
final readonly class RegisterCustomerData
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        #[SensitiveParameter]
        public string $password,
        public ?string $phone = null,
        public ?string $preferredLocale = null,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        $phone = isset($attributes['phone']) ? trim((string) $attributes['phone']) : null;
        $locale = isset($attributes['preferred_locale']) ? trim((string) $attributes['preferred_locale']) : null;

        return new self(
            firstName: NameNormalizer::normalize((string) ($attributes['first_name'] ?? '')),
            lastName: NameNormalizer::normalize((string) ($attributes['last_name'] ?? '')),
            email: EmailNormalizer::normalize((string) ($attributes['email'] ?? '')),
            password: (string) ($attributes['password'] ?? ''),
            phone: $phone === '' ? null : $phone,
            preferredLocale: $locale === '' ? null : $locale,
        );
    }
}
