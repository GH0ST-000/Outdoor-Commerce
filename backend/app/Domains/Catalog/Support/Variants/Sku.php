<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Support\Variants;

use InvalidArgumentException;

/**
 * SKU value object. Normalized to uppercase; allowed: A-Z 0-9 - _
 */
final readonly class Sku
{
    private function __construct(public string $value) {}

    public static function fromString(string $raw): self
    {
        $normalized = self::normalize($raw);
        self::assertValid($normalized);

        return new self($normalized);
    }

    public static function normalize(string $raw): string
    {
        return strtoupper(trim($raw));
    }

    public static function assertValid(string $normalized): void
    {
        $max = (int) config('catalog.variants.sku.max_length', 64);
        $pattern = (string) config('catalog.variants.sku.pattern', '/^[A-Z0-9_-]+$/');

        if ($normalized === '') {
            throw new InvalidArgumentException('SKU cannot be empty.');
        }
        if (strlen($normalized) > $max) {
            throw new InvalidArgumentException("SKU must be at most {$max} characters.");
        }
        if (! preg_match($pattern, $normalized)) {
            throw new InvalidArgumentException('SKU may only contain A-Z, 0-9, hyphen, and underscore.');
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
