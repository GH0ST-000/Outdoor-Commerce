<?php

declare(strict_types=1);

namespace App\Domains\Cart\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

final class CartIdempotencyReplayException extends DomainException
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        private readonly int $statusCode,
        private readonly array $body,
    ) {
        parent::__construct('Idempotent cart mutation replayed.', 'CART_IDEMPOTENCY_REPLAY');
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function body(): array
    {
        return $this->body;
    }
}
