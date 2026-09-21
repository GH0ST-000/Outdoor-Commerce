<?php

declare(strict_types=1);

namespace App\Domains\Catalog\PublicApi\Services;

use App\Domains\Catalog\PublicApi\Enums\PublicAvailabilityStatus;
use App\Domains\Inventory\DTOs\PublicAvailabilityData;

final class PublicAvailabilityPresenter
{
    /**
     * @return array{status: string, purchasable: bool, low_stock: bool}
     */
    public function present(PublicAvailabilityData $availability, bool $hasPrice): array
    {
        $status = $this->status($availability, $hasPrice);

        return [
            'status' => $status->value,
            'purchasable' => $hasPrice && $availability->availableToSell > 0,
            'low_stock' => $status === PublicAvailabilityStatus::LowStock,
        ];
    }

    public function status(PublicAvailabilityData $availability, bool $hasPrice): PublicAvailabilityStatus
    {
        if (! $hasPrice) {
            return PublicAvailabilityStatus::Unavailable;
        }

        if ($availability->availableToSell <= 0) {
            return PublicAvailabilityStatus::OutOfStock;
        }

        if ($availability->isLowStock) {
            return PublicAvailabilityStatus::LowStock;
        }

        return PublicAvailabilityStatus::InStock;
    }

    /**
     * @return array{status: string, purchasable: bool, low_stock: bool}
     */
    public function fromProjection(bool $isPublic, bool $isInStock, bool $isLowStock, bool $hasPrice): array
    {
        if (! $isPublic || ! $hasPrice) {
            return [
                'status' => PublicAvailabilityStatus::Unavailable->value,
                'purchasable' => false,
                'low_stock' => false,
            ];
        }

        if (! $isInStock) {
            return [
                'status' => PublicAvailabilityStatus::OutOfStock->value,
                'purchasable' => false,
                'low_stock' => false,
            ];
        }

        $status = $isLowStock ? PublicAvailabilityStatus::LowStock : PublicAvailabilityStatus::InStock;

        return [
            'status' => $status->value,
            'purchasable' => true,
            'low_stock' => $isLowStock,
        ];
    }
}
