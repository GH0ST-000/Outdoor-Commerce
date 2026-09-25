<?php

declare(strict_types=1);

namespace App\Domains\Shipping\DTOs;

final readonly class CancelProviderShipmentResultData
{
    public function __construct(
        public bool $cancelled,
        public bool $supported,
    ) {}
}
