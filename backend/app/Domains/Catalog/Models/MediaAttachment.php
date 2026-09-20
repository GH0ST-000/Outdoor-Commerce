<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Models;

use App\Domains\Catalog\Enums\MediaAttachmentRole;
use App\Domains\Catalog\Enums\MediaStatus;
use App\Domains\Catalog\Support\CatalogLocales;
use App\Models\User;
use Database\Factories\MediaAttachmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Binds an asset to a product or variant. Ordering, primary flag, focal point,
 * and localized alt text live here — the asset itself is owner-agnostic and
 * may legitimately be attached in more than one place.
 *
 * @property int $id
 * @property int $media_asset_id
 * @property string $mediable_type
 * @property int $mediable_id
 * @property MediaAttachmentRole $role
 * @property int $sort_order
 * @property bool $is_primary
 * @property string|null $focal_point_x
 * @property string|null $focal_point_y
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class MediaAttachment extends Model
{
    /** @use HasFactory<MediaAttachmentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'media_asset_id',
        'mediable_type',
        'mediable_id',
        'role',
        'sort_order',
        'is_primary',
        'focal_point_x',
        'focal_point_y',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => MediaAttachmentRole::class,
            'sort_order' => 'integer',
            'is_primary' => 'boolean',
            'mediable_id' => 'integer',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }

    /**
     * @return HasMany<MediaAttachmentTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(MediaAttachmentTranslation::class);
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
     * @param  Builder<MediaAttachment>  $query
     * @return Builder<MediaAttachment>
     */
    public function scopeGallery(Builder $query): Builder
    {
        return $query->where('role', MediaAttachmentRole::Gallery->value);
    }

    /**
     * @param  Builder<MediaAttachment>  $query
     * @return Builder<MediaAttachment>
     */
    public function scopeReady(Builder $query): Builder
    {
        return $query->whereHas(
            'asset',
            static fn (Builder $assets): Builder => $assets->where('status', MediaStatus::Ready->value),
        );
    }

    /**
     * @param  Builder<MediaAttachment>  $query
     * @return Builder<MediaAttachment>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function translation(?string $locale = null): ?MediaAttachmentTranslation
    {
        $locale ??= CatalogLocales::default();

        $translations = $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->get();

        return $translations->firstWhere('locale', $locale)
            ?? $translations->firstWhere('locale', CatalogLocales::fallback());
    }

    /**
     * Requested locale first, then the catalog fallback locale. Empty strings are
     * treated as missing so a blank English row cannot mask Georgian alt text.
     */
    public function altText(?string $locale = null): ?string
    {
        $locale ??= CatalogLocales::default();

        $translations = $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->get();

        foreach ([$locale, CatalogLocales::fallback()] as $candidate) {
            $value = $translations->firstWhere('locale', $candidate)?->alt_text;

            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }

    public function caption(?string $locale = null): ?string
    {
        $value = $this->translation($locale)?->caption;

        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    public function isReady(): bool
    {
        return $this->asset?->isReady() === true;
    }

    protected static function newFactory(): MediaAttachmentFactory
    {
        return MediaAttachmentFactory::new();
    }
}
