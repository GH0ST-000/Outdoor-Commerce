<?php

declare(strict_types=1);

namespace App\Domains\Geography\Models;

use App\Domains\Geography\Enums\SpatialDatasetStatus;
use App\Domains\Geography\Enums\SpatialDatasetType;
use App\Domains\Identity\Models\User;
use Database\Factories\SpatialDatasetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property int $spatial_source_id
 * @property string $name
 * @property string $slug
 * @property SpatialDatasetType $dataset_type
 * @property SpatialDatasetStatus $status
 * @property int $canonical_srid
 * @property string $jurisdiction_code
 * @property int|null $current_version_id
 * @property bool $is_fictional
 * @property-read SpatialSource $source
 */
class SpatialDataset extends Model
{
    /** @use HasFactory<SpatialDatasetFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'spatial_source_id',
        'name',
        'slug',
        'dataset_type',
        'jurisdiction_code',
        'description',
        'native_crs',
        'canonical_srid',
        'update_frequency',
        'status',
        'current_version_id',
        'is_fictional',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dataset_type' => SpatialDatasetType::class,
            'status' => SpatialDatasetStatus::class,
            'canonical_srid' => 'integer',
            'is_fictional' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $dataset): void {
            if ($dataset->public_id === null || $dataset->public_id === '') {
                $dataset->public_id = (string) Str::uuid();
            }
            if ($dataset->canonical_srid === null) {
                $dataset->canonical_srid = 4326;
            }
        });
    }

    /**
     * @return BelongsTo<SpatialSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(SpatialSource::class, 'spatial_source_id');
    }

    /**
     * @return BelongsTo<SpatialDatasetVersion, $this>
     */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(SpatialDatasetVersion::class, 'current_version_id');
    }

    /**
     * @return HasMany<SpatialDatasetVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(SpatialDatasetVersion::class);
    }

    /**
     * @return HasMany<SpatialZone, $this>
     */
    public function zones(): HasMany
    {
        return $this->hasMany(SpatialZone::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function newFactory(): SpatialDatasetFactory
    {
        return SpatialDatasetFactory::new();
    }
}
