<?php

declare(strict_types=1);

namespace App\Domains\Geography\Support;

use App\Domains\Geography\Exceptions\SpatialException;

final readonly class BoundingBox
{
    public function __construct(
        public Longitude $west,
        public Latitude $south,
        public Longitude $east,
        public Latitude $north,
    ) {
        if ($this->west->value > $this->east->value) {
            throw SpatialException::invalidBoundingBox('West longitude must not be greater than east.');
        }
        if ($this->south->value > $this->north->value) {
            throw SpatialException::invalidBoundingBox('South latitude must not be greater than north.');
        }
    }

    public static function fromEdges(float $west, float $south, float $east, float $north): self
    {
        return new self(
            new Longitude($west),
            new Latitude($south),
            new Longitude($east),
            new Latitude($north),
        );
    }

    public function contains(GeoCoordinate $point): bool
    {
        return $point->longitude->value >= $this->west->value
            && $point->longitude->value <= $this->east->value
            && $point->latitude->value >= $this->south->value
            && $point->latitude->value <= $this->north->value;
    }

    public function intersects(self $other): bool
    {
        return $this->west->value <= $other->east->value
            && $this->east->value >= $other->west->value
            && $this->south->value <= $other->north->value
            && $this->north->value >= $other->south->value;
    }

    public function areaSpanProduct(): float
    {
        return max(0.0, $this->east->value - $this->west->value)
            * max(0.0, $this->north->value - $this->south->value);
    }

    public function expanded(float $degrees): self
    {
        $pad = abs($degrees);

        return self::fromEdges(
            max(-180.0, $this->west->value - $pad),
            max(-90.0, $this->south->value - $pad),
            min(180.0, $this->east->value + $pad),
            min(90.0, $this->north->value + $pad),
        );
    }

    public function spanDegrees(): float
    {
        return max($this->east->value - $this->west->value, $this->north->value - $this->south->value);
    }

    /**
     * @return array{west: float, south: float, east: float, north: float, srid: int}
     */
    public function toArray(): array
    {
        return [
            'west' => $this->west->value,
            'south' => $this->south->value,
            'east' => $this->east->value,
            'north' => $this->north->value,
            'srid' => 4326,
        ];
    }
}
