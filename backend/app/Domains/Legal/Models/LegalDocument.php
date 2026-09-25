<?php

declare(strict_types=1);

namespace App\Domains\Legal\Models;

use App\Domains\Legal\Enums\LegalDocumentStatus;
use App\Domains\Legal\Enums\LegalDocumentType;
use Database\Factories\LegalDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property int $legal_source_id
 * @property int $legal_authority_id
 * @property string $title
 * @property string $slug
 * @property LegalDocumentType $document_type
 * @property LegalDocumentStatus $status
 * @property string|null $official_url
 * @property int|null $current_version_id
 */
class LegalDocument extends Model
{
    /** @use HasFactory<LegalDocumentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'legal_source_id',
        'legal_authority_id',
        'title',
        'slug',
        'official_identifier',
        'document_type',
        'jurisdiction_code',
        'language_code',
        'official_url',
        'publication_date',
        'original_effective_date',
        'repealed_at',
        'status',
        'current_version_id',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_type' => LegalDocumentType::class,
            'status' => LegalDocumentStatus::class,
            'publication_date' => 'date',
            'original_effective_date' => 'date',
            'repealed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->public_id ??= (string) Str::uuid();
        });
    }

    protected static function newFactory(): LegalDocumentFactory
    {
        return LegalDocumentFactory::new();
    }

    /**
     * @return BelongsTo<LegalSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(LegalSource::class, 'legal_source_id');
    }

    /**
     * @return BelongsTo<LegalAuthority, $this>
     */
    public function authority(): BelongsTo
    {
        return $this->belongsTo(LegalAuthority::class, 'legal_authority_id');
    }

    /**
     * @return HasMany<LegalDocumentVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(LegalDocumentVersion::class);
    }

    /**
     * @return BelongsTo<LegalDocumentVersion, $this>
     */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(LegalDocumentVersion::class, 'current_version_id');
    }
}
