<?php

declare(strict_types=1);

namespace App\Domains\Geography\Models;

use App\Domains\Geography\Enums\SpatialImportStatus;
use App\Domains\Identity\Models\User;
use Database\Factories\SpatialImportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property int $spatial_dataset_version_id
 * @property SpatialImportStatus $status
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property int $triggered_by
 * @property-read SpatialDatasetVersion $datasetVersion
 */
class SpatialImport extends Model
{
    /** @use HasFactory<SpatialImportFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'spatial_dataset_version_id',
        'status',
        'started_at',
        'completed_at',
        'features_discovered',
        'features_validated',
        'features_imported',
        'features_rejected',
        'warnings_count',
        'errors_count',
        'triggered_by',
        'error_summary',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SpatialImportStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'features_discovered' => 'integer',
            'features_validated' => 'integer',
            'features_imported' => 'integer',
            'features_rejected' => 'integer',
            'warnings_count' => 'integer',
            'errors_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $import): void {
            if ($import->public_id === null || $import->public_id === '') {
                $import->public_id = (string) Str::uuid();
            }
        });
    }

    /**
     * @return BelongsTo<SpatialDatasetVersion, $this>
     */
    public function datasetVersion(): BelongsTo
    {
        return $this->belongsTo(SpatialDatasetVersion::class, 'spatial_dataset_version_id');
    }

    /**
     * @return HasMany<SpatialImportError, $this>
     */
    public function errors(): HasMany
    {
        return $this->hasMany(SpatialImportError::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    protected static function newFactory(): SpatialImportFactory
    {
        return SpatialImportFactory::new();
    }
}
