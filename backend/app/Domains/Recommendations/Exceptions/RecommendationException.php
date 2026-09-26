<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;
use App\Domains\Shared\Exceptions\ProvidesErrorDetails;

class RecommendationException extends DomainException implements ProvidesErrorDetails
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        string $message,
        string $errorCode,
        private readonly array $details = [],
        private readonly int $httpStatus = 422,
    ) {
        parent::__construct($message, $errorCode);
    }

    /**
     * @return array<string, mixed>
     */
    public function errorDetails(): array
    {
        return $this->details;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public static function invalid(string $message, array $details = []): self
    {
        return new self($message, 'RECOMMENDATION_INVALID', $details);
    }

    public static function notFound(string $subject): self
    {
        return new self($subject.' was not found.', 'RECOMMENDATION_NOT_FOUND', [], 404);
    }

    public static function conflict(string $message): self
    {
        return new self($message, 'RECOMMENDATION_CONFLICT', [], 409);
    }
}
