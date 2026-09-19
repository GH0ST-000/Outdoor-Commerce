<?php

declare(strict_types=1);

namespace App\Domains\Shared\Support;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Stringable;

/**
 * Immutable correlation / request identifier used across HTTP and logs.
 */
final readonly class CorrelationId implements Stringable
{
    public const REQUEST_ATTRIBUTE = 'correlation_id';

    public const HEADER = 'X-Request-ID';

    private const MAX_LENGTH = 64;

    private function __construct(
        private string $value,
    ) {}

    public static function generate(): self
    {
        return new self((string) Str::uuid());
    }

    public static function fromTrusted(string $value): self
    {
        $normalized = strtolower(trim($value));

        if (! self::isValidFormat($normalized)) {
            throw new InvalidArgumentException('Invalid correlation ID format.');
        }

        return new self($normalized);
    }

    /**
     * Accept a client-supplied header value only when it is a safe UUID.
     * Invalid or oversized values are ignored by returning null.
     */
    public static function tryFromHeader(?string $header): ?self
    {
        if ($header === null) {
            return null;
        }

        $trimmed = trim($header);

        if ($trimmed === '' || strlen($trimmed) > self::MAX_LENGTH) {
            return null;
        }

        $normalized = strtolower($trimmed);

        if (! self::isValidFormat($normalized)) {
            return null;
        }

        return new self($normalized);
    }

    public static function isValidFormat(string $value): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            strtolower($value),
        );
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
