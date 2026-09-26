<?php

declare(strict_types=1);

namespace App\Domains\Geography\Services;

use App\Domains\Geography\Enums\SpatialValidationStatus;
use App\Domains\Geography\Exceptions\SpatialException;
use App\Domains\Geography\Support\MultipolygonGeometry;

final class GeometryValidationService
{
    /**
     * @return array{status: SpatialValidationStatus, warnings: list<string>, checksum: string, vertex_count: int}
     */
    public function validate(MultipolygonGeometry $geometry, ?MultipolygonGeometry $previous = null): array
    {
        if ($geometry->polygonCount() < 1 || $geometry->vertexCount() < 3) {
            throw SpatialException::invalidGeometry('Geometry is empty.');
        }

        $warnings = [];
        $bbox = $geometry->boundingBox();
        if ($bbox->spanDegrees() > 40.0) {
            $warnings[] = 'Geometry covers a suspiciously large area.';
        }
        if ($geometry->vertexCount() > (int) config('spatial.import.max_vertices_per_feature', 50000)) {
            throw SpatialException::invalidGeometry('Vertex count exceeds the configured limit.');
        }

        if ($previous !== null) {
            $previousBox = $previous->boundingBox();
            $areaChange = abs($bbox->areaSpanProduct() - $previousBox->areaSpanProduct());
            $baseline = max($previousBox->areaSpanProduct(), 0.000001);
            if (($areaChange / $baseline) > 0.5) {
                $warnings[] = 'Bounding coverage changed by more than 50 percent versus the previous version.';
            }
            $centroidShift = hypot(
                $geometry->centroid()->longitude->value - $previous->centroid()->longitude->value,
                $geometry->centroid()->latitude->value - $previous->centroid()->latitude->value,
            );
            if ($centroidShift > 1.0) {
                $warnings[] = 'Centroid moved by more than one degree versus the previous version.';
            }
        }

        return [
            'status' => $warnings === [] ? SpatialValidationStatus::Valid : SpatialValidationStatus::Warning,
            'warnings' => $warnings,
            'checksum' => $geometry->checksum(),
            'vertex_count' => $geometry->vertexCount(),
        ];
    }
}
