<?php

declare(strict_types=1);

namespace App\Domains\Geography\DTOs;

use App\Domains\Geography\Enums\SpatialRelation;
use App\Domains\Geography\Support\GeoCoordinate;

final readonly class PointClassificationData
{
    public function __construct(
        public SpatialRelation $relation,
        public bool $nearBoundary,
        public float $boundaryDistanceDegrees,
        public GeoCoordinate $point,
        public float $warningDegrees,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'relation' => $this->relation->value,
            'near_boundary' => $this->nearBoundary,
            'boundary_distance_degrees' => $this->boundaryDistanceDegrees,
            'warning_tolerance_degrees' => $this->warningDegrees,
            'longitude' => $this->point->longitude->value,
            'latitude' => $this->point->latitude->value,
            'srid' => 4326,
            'coordinate_order' => 'longitude,latitude',
        ];
    }
}
