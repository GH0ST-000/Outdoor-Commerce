<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Support;

use App\Domains\Shipping\Exceptions\ShipmentException;

final class TrackingNumberNormalizer
{
    public function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $max = max(1, (int) config('shipping.tracking_number_max_length', 64));
        if (strlen($trimmed) > $max || preg_match('/[\x00-\x1F\x7F]/', $trimmed) === 1) {
            throw ShipmentException::trackingNumberInvalid();
        }

        return $trimmed;
    }
}
