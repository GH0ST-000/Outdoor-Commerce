<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Models;

use App\Domains\Catalog\Enums\MediaFormat;
use App\Domains\Catalog\Enums\MediaPreset;
use App\Domains\Catalog\Enums\MediaStatus;
use App\Models\User;
use Database\Factories\MediaAssetFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A stored original plus the state of its derivative pipeline.
 *
 * `original_disk` / `original_path` are internal: they are never serialized into
 * an API response. The public surface is always a derivative URL.
 *
 * @property int $id
 * @property string $uuid
 * @property MediaStatus $status
 * @property string $original_disk
 * @property string $original_path
 * @property string|null $original_filename
 * @property string $original_extension
 * @property string $mime_type
 * @property int $byte_size
 * @property int|null $width
 * @property int|null $height
 * @property string|null $checksum_sha256
 * @property int $attempts
 * @property string|null $failure_code
 * @property string|null $failure_message
 * @property Carbon|null $processing_started_at
 * @property Carbon|null $processed_at
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class MediaAsset extends Model
{
    /** @use HasFactory<MediaAssetFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'status',
        'original_disk',
        'original_path',
        'original_filename',
        'original_extension',
        'mime_type',
        'byte_size',
        'width',
        'height',
        'checksum_sha256',
        'attempts',
        'failure_code',
        'failure_message',
        'processing_started_at',
        'processed_at',
        'created_by',
        'updated_by',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'original_disk',
        'original_path',
        'failure_message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MediaStatus::class,
            'byte_size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'attempts' => 'integer',
            'processing_started_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<MediaDerivative, $this>
     */
    public function derivatives(): HasMany
    {
        return $this->hasMany(MediaDerivative::class);
    }

    /**
     * @return HasMany<MediaAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(MediaAttachment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @param  Builder<MediaAsset>  $query
     * @return Builder<MediaAsset>
     */
    public function scopeReady(Builder $query): Builder
    {
        return $query->where('status', MediaStatus::Ready->value);
    }

    /**
     * @param  Builder<MediaAsset>  $query
     * @return Builder<MediaAsset>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', MediaStatus::Failed->value);
    }

    public function isReady(): bool
    {
        return $this->status === MediaStatus::Ready;
    }

    public function derivative(MediaPreset $preset, MediaFormat $format): ?MediaDerivative
    {
        $derivatives = $this->relationLoaded('derivatives')
            ? $this->derivatives
            : $this->derivatives()->get();

        return $derivatives
            ->first(static fn (MediaDerivative $derivative): bool => $derivative->preset === $preset
                && $derivative->format === $format);
    }

    protected static function newFactory(): MediaAssetFactory
    {
        return MediaAssetFactory::new();
    }
}
