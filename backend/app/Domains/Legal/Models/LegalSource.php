<?php

declare(strict_types=1);

namespace App\Domains\Legal\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Legal\Enums\LegalSourceType;
use App\Domains\Legal\Enums\LegalVerificationStatus;
use Database\Factories\LegalSourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property int $legal_authority_id
 * @property string $name
 * @property string $slug
 * @property LegalSourceType $source_type
 * @property string|null $official_base_url
 * @property string|null $allowed_domain
 * @property LegalVerificationStatus $verification_status
 * @property bool $is_active
 * @property bool $monitor_for_changes
 * @property Carbon|null $verified_at
 * @property Carbon|null $last_checked_at
 * @property Carbon|null $last_change_detected_at
 */
class LegalSource extends Model
{
    /** @use HasFactory<LegalSourceFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $hidden = ['notes', 'last_checksum'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'legal_authority_id',
        'name',
        'slug',
        'source_type',
        'official_base_url',
        'allowed_domain',
        'language_code',
        'jurisdiction_code',
        'trust_level',
        'verification_status',
        'verified_at',
        'verified_by',
        'notes',
        'is_active',
        'monitor_for_changes',
        'last_checked_at',
        'last_change_detected_at',
        'last_etag',
        'last_modified_header',
        'last_content_length',
        'last_checksum',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_type' => LegalSourceType::class,
            'verification_status' => LegalVerificationStatus::class,
            'verified_at' => 'datetime',
            'is_active' => 'boolean',
            'monitor_for_changes' => 'boolean',
            'last_checked_at' => 'datetime',
            'last_change_detected_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->public_id ??= (string) Str::uuid();
        });
    }

    protected static function newFactory(): LegalSourceFactory
    {
        return LegalSourceFactory::new();
    }

    public function isPublishableSource(): bool
    {
        return $this->is_active && $this->verification_status === LegalVerificationStatus::Verified;
    }

    /**
     * @return BelongsTo<LegalAuthority, $this>
     */
    public function authority(): BelongsTo
    {
        return $this->belongsTo(LegalAuthority::class, 'legal_authority_id');
    }

    /**
     * @return HasMany<LegalDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(LegalDocument::class);
    }

    /**
     * @return HasMany<LegalChangeDetection, $this>
     */
    public function changeDetections(): HasMany
    {
        return $this->hasMany(LegalChangeDetection::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
