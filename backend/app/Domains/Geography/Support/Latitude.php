<?php

declare(strict_types=1);

namespace App\Domains\Geography\Support;

use App\Domains\Geography\Exceptions\SpatialException;

final readonly class Latitude
{
    public function __construct(public float $value)
    {
        if (! is_finite($value) || $value < -90.0 || $value > 90.0) {
            throw SpatialException::invalidCoordinate('Latitude must be between -90 and 90.');
        }
    }
}
