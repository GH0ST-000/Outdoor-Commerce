<?php

declare(strict_types=1);

namespace App\Domains\Geography\Models;

use App\Domains\Geography\Enums\SpatialZoneStatus;
use App\Domains\Geography\Enums\SpatialZoneType;
use App\Domains\Identity\Models\User;
use Database\Factories\SpatialZoneFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property int $spatial_dataset_id
 * @property string|null $external_identifier
 * @property string $slug
 * @property SpatialZoneType $zone_type
 * @property string $jurisdiction_code
 * @property string|null $region_code
 * @property string $default_name
 * @property string|null $description
 * @property SpatialZoneStatus $status
 * @property bool $is_fictional
 * @property-read SpatialDataset $dataset
 * @property-read Collection<int, SpatialZoneTranslation> $translations
 */
class SpatialZone extends Model
{
    /** @use HasFactory<SpatialZoneFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'spatial_dataset_id',
        'external_identifier',
        'slug',
        'zone_type',
        'jurisdiction_code',
        'region_code',
        'default_name',
        'description',
        'status',
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
            'zone_type' => SpatialZoneType::class,
            'status' => SpatialZoneStatus::class,
            'is_fictional' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $zone): void {
            if ($zone->public_id === null || $zone->public_id === '') {
                $zone->public_id = (string) Str::uuid();
            }
        });
    }

    public function localizedName(string $locale): string
    {
        foreach ($this->translations as $translation) {
            if ($translation->locale === $locale) {
                return $translation->name;
            }
        }

        return $this->default_name;
    }

    public function localizedDescription(string $locale): ?string
    {
        foreach ($this->translations as $translation) {
            if ($translation->locale === $locale && is_string($translation->short_description) && $translation->short_description !== '') {
                return $translation->short_description;
            }
        }

        return $this->description;
    }

    /**
     * @return BelongsTo<SpatialDataset, $this>
     */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(SpatialDataset::class, 'spatial_dataset_id');
    }

    /**
     * @return HasMany<SpatialZoneTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(SpatialZoneTranslation::class);
    }

    /**
     * @return HasMany<SpatialZoneGeometryVersion, $this>
     */
    public function geometryVersions(): HasMany
    {
        return $this->hasMany(SpatialZoneGeometryVersion::class);
    }

    /**
     * @return HasMany<LegalRuleSpatialZone, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(LegalRuleSpatialZone::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function newFactory(): SpatialZoneFactory
    {
        return SpatialZoneFactory::new();
    }
}
