<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Models;

use App\Domains\Catalog\Enums\MediaFormat;
use App\Domains\Catalog\Enums\MediaPreset;
use Database\Factories\MediaDerivativeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One generated rendition. Hard-deleted and rewritten on reprocess, so the row
 * set for an asset always matches what is actually on the derivatives disk.
 *
 * @property int $id
 * @property int $media_asset_id
 * @property MediaPreset $preset
 * @property MediaFormat $format
 * @property string $disk
 * @property string $path
 * @property int $width
 * @property int $height
 * @property int $byte_size
 */
class MediaDerivative extends Model
{
    /** @use HasFactory<MediaDerivativeFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'media_asset_id',
        'preset',
        'format',
        'disk',
        'path',
        'width',
        'height',
        'byte_size',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'preset' => MediaPreset::class,
            'format' => MediaFormat::class,
            'width' => 'integer',
            'height' => 'integer',
            'byte_size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }

    protected static function newFactory(): MediaDerivativeFactory
    {
        return MediaDerivativeFactory::new();
    }
}
