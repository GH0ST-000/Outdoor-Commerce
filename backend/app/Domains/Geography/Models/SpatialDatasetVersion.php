<?php

declare(strict_types=1);

namespace App\Domains\Geography\Models;

use App\Domains\Geography\Enums\SpatialImportStatus;
use App\Domains\Geography\Enums\SpatialReviewStatus;
use App\Domains\Identity\Models\User;
use Database\Factories\SpatialDatasetVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property int $spatial_dataset_id
 * @property string $version_label
 * @property string $content_checksum
 * @property string $storage_disk
 * @property string $storage_path
 * @property string $original_filename
 * @property int $file_size
 * @property int|null $feature_count
 * @property SpatialImportStatus $import_status
 * @property SpatialReviewStatus $review_status
 * @property Carbon $effective_from
 * @property Carbon|null $effective_until
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $published_at
 * @property array<string, mixed>|null $property_mapping
 * @property array<string, mixed>|null $validation_summary
 * @property string|null $source_crs
 * @property-read SpatialDataset $dataset
 */
class SpatialDatasetVersion extends Model
{
    /** @use HasFactory<SpatialDatasetVersionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $hidden = ['storage_path', 'storage_disk'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'spatial_dataset_id',
        'version_label',
        'source_url',
        'source_published_at',
        'effective_from',
        'effective_until',
        'retrieved_at',
        'retrieved_by',
        'content_checksum',
        'checksum_algorithm',
        'storage_disk',
        'storage_path',
        'original_filename',
        'mime_type',
        'file_size',
        'feature_count',
        'source_crs',
        'property_mapping',
        'import_status',
        'review_status',
        'validation_summary',
        'change_summary',
        'supersedes_version_id',
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
            'source_published_at' => 'datetime',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
            'retrieved_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
            'property_mapping' => 'array',
            'validation_summary' => 'array',
            'import_status' => SpatialImportStatus::class,
            'review_status' => SpatialReviewStatus::class,
            'file_size' => 'integer',
            'feature_count' => 'integer',
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

    public function isPublished(): bool
    {
        return $this->review_status === SpatialReviewStatus::Published;
    }

    public function isImmutable(): bool
    {
        return in_array($this->review_status, [
            SpatialReviewStatus::Published,
            SpatialReviewStatus::Superseded,
        ], true);
    }

    /**
     * @return BelongsTo<SpatialDataset, $this>
     */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(SpatialDataset::class, 'spatial_dataset_id');
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_version_id');
    }

    /**
     * @return HasMany<SpatialZoneGeometryVersion, $this>
     */
    public function geometryVersions(): HasMany
    {
        return $this->hasMany(SpatialZoneGeometryVersion::class);
    }

    /**
     * @return HasMany<SpatialImport, $this>
     */
    public function imports(): HasMany
    {
        return $this->hasMany(SpatialImport::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function retrievedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'retrieved_by');
    }

    protected static function newFactory(): SpatialDatasetVersionFactory
    {
        return SpatialDatasetVersionFactory::new();
    }
}
