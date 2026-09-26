<?php

declare(strict_types=1);

namespace App\Domains\Geography\Models;

use App\Domains\Geography\Enums\SpatialSourceType;
use App\Domains\Geography\Enums\SpatialVerificationStatus;
use App\Domains\Identity\Models\User;
use App\Domains\Legal\Models\LegalAuthority;
use App\Domains\Legal\Models\LegalSource;
use Database\Factories\SpatialSourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string $slug
 * @property SpatialSourceType $source_type
 * @property SpatialVerificationStatus $verification_status
 * @property bool $is_active
 * @property bool $is_fictional
 * @property Carbon|null $verified_at
 */
class SpatialSource extends Model
{
    /** @use HasFactory<SpatialSourceFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $hidden = ['allowed_usage_notes'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'legal_source_id',
        'legal_authority_id',
        'name',
        'slug',
        'source_type',
        'official_url',
        'official_identifier',
        'publisher_name',
        'jurisdiction_code',
        'license_name',
        'license_url',
        'attribution_text',
        'allowed_usage_notes',
        'verification_status',
        'verified_at',
        'verified_by',
        'is_active',
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
            'source_type' => SpatialSourceType::class,
            'verification_status' => SpatialVerificationStatus::class,
            'verified_at' => 'datetime',
            'is_active' => 'boolean',
            'is_fictional' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $source): void {
            if ($source->public_id === null || $source->public_id === '') {
                $source->public_id = (string) Str::uuid();
            }
        });
    }

    public function canSupportPublication(): bool
    {
        return $this->is_active && $this->verification_status === SpatialVerificationStatus::Verified;
    }

    /**
     * @return BelongsTo<LegalSource, $this>
     */
    public function legalSource(): BelongsTo
    {
        return $this->belongsTo(LegalSource::class);
    }

    /**
     * @return BelongsTo<LegalAuthority, $this>
     */
    public function legalAuthority(): BelongsTo
    {
        return $this->belongsTo(LegalAuthority::class);
    }

    /**
     * @return HasMany<SpatialDataset, $this>
     */
    public function datasets(): HasMany
    {
        return $this->hasMany(SpatialDataset::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    protected static function newFactory(): SpatialSourceFactory
    {
        return SpatialSourceFactory::new();
    }
}
