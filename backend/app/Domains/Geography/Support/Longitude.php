<?php

declare(strict_types=1);

namespace App\Domains\Geography\Support;

use App\Domains\Geography\Exceptions\SpatialException;

final readonly class Longitude
{
    public function __construct(public float $value)
    {
        if (! is_finite($value) || $value < -180.0 || $value > 180.0) {
            throw SpatialException::invalidCoordinate('Longitude must be between -180 and 180.');
        }
    }
}
