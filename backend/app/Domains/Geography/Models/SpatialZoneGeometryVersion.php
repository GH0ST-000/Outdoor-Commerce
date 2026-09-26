<?php

declare(strict_types=1);

namespace App\Domains\Geography\Models;

use App\Domains\Geography\Enums\SpatialGeometryVersionStatus;
use App\Domains\Geography\Enums\SpatialValidationStatus;
use App\Domains\Geography\Support\BoundingBox;
use App\Domains\Geography\Support\GeoCoordinate;
use App\Domains\Geography\Support\MultipolygonGeometry;
use App\Domains\Geography\Support\SpatialDriver;
use App\Domains\Identity\Models\User;
use Database\Factories\SpatialZoneGeometryVersionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property int $spatial_zone_id
 * @property int $spatial_dataset_version_id
 * @property string $geometry_wkt
 * @property string $geometry_checksum
 * @property SpatialGeometryVersionStatus $status
 * @property SpatialValidationStatus $validation_status
 * @property int $vertex_count
 * @property Carbon $effective_from
 * @property Carbon|null $effective_until
 * @property Carbon|null $published_at
 * @property-read SpatialZone $zone
 * @property-read SpatialDatasetVersion $datasetVersion
 */
class SpatialZoneGeometryVersion extends Model
{
    /** @use HasFactory<SpatialZoneGeometryVersionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'spatial_zone_id',
        'spatial_dataset_version_id',
        'geometry_wkt',
        'min_longitude',
        'min_latitude',
        'max_longitude',
        'max_latitude',
        'centroid_longitude',
        'centroid_latitude',
        'vertex_count',
        'polygon_count',
        'area_square_meters',
        'geometry_checksum',
        'source_feature_identifier',
        'effective_from',
        'effective_until',
        'status',
        'validation_status',
        'validation_warnings',
        'reviewed_at',
        'reviewed_by',
        'published_at',
        'published_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_longitude' => 'float',
            'min_latitude' => 'float',
            'max_longitude' => 'float',
            'max_latitude' => 'float',
            'centroid_longitude' => 'float',
            'centroid_latitude' => 'float',
            'vertex_count' => 'integer',
            'polygon_count' => 'integer',
            'area_square_meters' => 'integer',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
            'status' => SpatialGeometryVersionStatus::class,
            'validation_status' => SpatialValidationStatus::class,
            'validation_warnings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $version): void {
            if ($version->public_id === null || $version->public_id === '') {
                $version->public_id = (string) Str::uuid();
            }
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function recordCanonical(array $attributes, MultipolygonGeometry $geometry): self
    {
        $attributes['public_id'] ??= (string) Str::uuid();
        $attributes['created_at'] = self::timestamp($attributes['created_at'] ?? now());
        $attributes['updated_at'] = self::timestamp($attributes['updated_at'] ?? now());
        foreach (['effective_from', 'effective_until', 'reviewed_at', 'published_at'] as $column) {
            if (array_key_exists($column, $attributes) && $attributes[$column] !== null) {
                $attributes[$column] = self::timestamp($attributes[$column]);
            }
        }
        if (isset($attributes['validation_warnings']) && is_array($attributes['validation_warnings'])) {
            $attributes['validation_warnings'] = json_encode($attributes['validation_warnings']);
        }

        if (SpatialDriver::supportsNativeGeometry()) {
            $columns = array_keys($attributes);
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $quoted = implode(', ', array_map(static fn (string $column): string => '`'.$column.'`', $columns));
            DB::insert(
                'INSERT INTO spatial_zone_geometry_versions ('.$quoted.', `geometry`) VALUES ('.$placeholders.', ST_GeomFromText(?, 4326))',
                [...array_values($attributes), $geometry->toWkt()],
            );
            $id = (int) DB::getPdo()->lastInsertId();
        } else {
            $id = (int) DB::table('spatial_zone_geometry_versions')->insertGetId($attributes);
        }

        return self::query()->findOrFail($id);
    }

    private static function timestamp(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return (string) $value;
    }

    public function geometry(): MultipolygonGeometry
    {
        return MultipolygonGeometry::fromWkt($this->geometry_wkt);
    }

    public function boundingBox(): BoundingBox
    {
        return BoundingBox::fromEdges(
            (float) $this->min_longitude,
            (float) $this->min_latitude,
            (float) $this->max_longitude,
            (float) $this->max_latitude,
        );
    }

    public function centroid(): GeoCoordinate
    {
        return GeoCoordinate::fromLngLat((float) $this->centroid_longitude, (float) $this->centroid_latitude);
    }

    public function isPublished(): bool
    {
        return $this->status === SpatialGeometryVersionStatus::Published;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublishedEffective(Builder $query, Carbon $at): Builder
    {
        return $query
            ->where('status', SpatialGeometryVersionStatus::Published)
            ->where('effective_from', '<=', $at)
            ->where(function (Builder $builder) use ($at): void {
                $builder->whereNull('effective_until')
                    ->orWhere('effective_until', '>', $at);
            });
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeIntersectingBbox(Builder $query, BoundingBox $bbox): Builder
    {
        return $query
            ->where('min_longitude', '<=', $bbox->east->value)
            ->where('max_longitude', '>=', $bbox->west->value)
            ->where('min_latitude', '<=', $bbox->north->value)
            ->where('max_latitude', '>=', $bbox->south->value);
    }

    /**
     * @return BelongsTo<SpatialZone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(SpatialZone::class, 'spatial_zone_id');
    }

    /**
     * @return BelongsTo<SpatialDatasetVersion, $this>
     */
    public function datasetVersion(): BelongsTo
    {
        return $this->belongsTo(SpatialDatasetVersion::class, 'spatial_dataset_version_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    protected static function newFactory(): SpatialZoneGeometryVersionFactory
    {
        return SpatialZoneGeometryVersionFactory::new();
    }
}
