<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

final class ShipmentIdempotencyReplayException extends DomainException
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        private readonly int $statusCode,
        private readonly array $body,
    ) {
        parent::__construct('Idempotent shipment request replay.', 'SHIPMENT_IDEMPOTENCY_REPLAY');
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
