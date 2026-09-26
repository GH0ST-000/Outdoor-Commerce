<?php

declare(strict_types=1);

namespace App\Domains\Geography\Support;

final readonly class GeoCoordinate
{
    public function __construct(
        public Longitude $longitude,
        public Latitude $latitude,
    ) {}

    public static function fromLngLat(float $longitude, float $latitude): self
    {
        return new self(new Longitude($longitude), new Latitude($latitude));
    }

    /**
     * @return array{0: float, 1: float}
     */
    public function toGeoJsonPosition(): array
    {
        return [$this->longitude->value, $this->latitude->value];
    }
}
