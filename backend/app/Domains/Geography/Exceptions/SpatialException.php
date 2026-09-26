<?php

declare(strict_types=1);

namespace App\Domains\Geography\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;
use App\Domains\Shared\Exceptions\ProvidesErrorDetails;

final class SpatialException extends DomainException implements ProvidesErrorDetails
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        string $message,
        string $errorCode,
        private readonly array $details = [],
        private readonly int $httpStatus = 422,
    ) {
        parent::__construct($message, $errorCode);
    }

    /**
     * @return array<string, mixed>
     */
    public function errorDetails(): array
    {
        return $this->details;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public static function notFound(string $subject = 'Spatial record'): self
    {
        return new self($subject.' was not found.', 'SPATIAL_NOT_FOUND', [], 404);
    }

    public static function invalidCoordinate(string $reason): self
    {
        return new self($reason, 'SPATIAL_COORDINATE_INVALID');
    }

    public static function invalidBoundingBox(string $reason): self
    {
        return new self($reason, 'SPATIAL_BBOX_INVALID');
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function invalidGeometry(string $reason, array $details = []): self
    {
        return new self($reason, 'SPATIAL_GEOMETRY_INVALID', $details);
    }

    public static function unsupportedCrs(string $crs): self
    {
        return new self(
            'Only SRID 4326 / CRS84 GeoJSON is accepted. Reprojection is not available.',
            'SPATIAL_CRS_UNSUPPORTED',
            ['crs' => $crs],
        );
    }

    public static function fileRejected(string $reason): self
    {
        return new self($reason, 'SPATIAL_FILE_REJECTED');
    }

    public static function checksumConflict(): self
    {
        return new self('A dataset version with this checksum already exists.', 'SPATIAL_CHECKSUM_CONFLICT');
    }

    public static function versionImmutable(): self
    {
        return new self('A published spatial version cannot be overwritten. Create a new version.', 'SPATIAL_VERSION_IMMUTABLE');
    }

    public static function invalidTransition(string $from, string $to): self
    {
        return new self(
            'This spatial status change is not allowed.',
            'SPATIAL_INVALID_TRANSITION',
            ['from' => $from, 'to' => $to],
        );
    }

    public static function sourceUnverified(): self
    {
        return new self('The spatial source must be verified and active before publication.', 'SPATIAL_SOURCE_UNVERIFIED');
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function publicationInvalid(array $details = []): self
    {
        return new self('This spatial dataset cannot be published.', 'SPATIAL_PUBLICATION_INVALID', $details);
    }

    public static function viewportRejected(string $reason): self
    {
        return new self($reason, 'SPATIAL_VIEWPORT_REJECTED');
    }

    public static function invalidQuery(string $reason): self
    {
        return new self($reason, 'SPATIAL_QUERY_INVALID');
    }

    public static function permissionRequired(): self
    {
        return new self('You do not have permission to perform this spatial action.', 'SPATIAL_PERMISSION_REQUIRED', [], 403);
    }

    public static function mappingInvalid(string $reason): self
    {
        return new self($reason, 'SPATIAL_MAPPING_INVALID');
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function importFailed(string $reason, array $details = []): self
    {
        return new self($reason, 'SPATIAL_IMPORT_FAILED', $details);
    }

    public static function concurrentPublication(): self
    {
        return new self('Another publication is already in progress for this dataset.', 'SPATIAL_PUBLICATION_LOCKED', [], 409);
    }
}
