<?php

declare(strict_types=1);

namespace App\Domains\Geography\Models;

use App\Domains\Geography\Enums\SpatialFeatureErrorStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $spatial_import_id
 * @property string|null $source_feature_identifier
 * @property string $error_code
 * @property string $message
 * @property array<string, mixed>|null $context
 * @property SpatialFeatureErrorStatus $resolution_status
 */
class SpatialImportError extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'spatial_import_id',
        'source_feature_identifier',
        'error_code',
        'message',
        'context',
        'resolution_status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'resolution_status' => SpatialFeatureErrorStatus::class,
        ];
    }

    /**
     * @return BelongsTo<SpatialImport, $this>
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(SpatialImport::class, 'spatial_import_id');
    }
}
