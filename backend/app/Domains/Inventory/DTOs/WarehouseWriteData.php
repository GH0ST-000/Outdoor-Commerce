<?php

declare(strict_types=1);

namespace App\Domains\Inventory\DTOs;

use App\Domains\Inventory\Enums\WarehouseStatus;

final readonly class WarehouseWriteData
{
    public function __construct(
        public string $code,
        public string $name,
        public WarehouseStatus $status,
        public bool $isDefault,
        public string $countryCode,
        public ?string $city,
        public ?string $addressLine1,
        public ?string $addressLine2,
        public ?string $postalCode,
        public ?float $latitude,
        public ?float $longitude,
    ) {}
}
