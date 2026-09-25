<?php

declare(strict_types=1);

namespace App\Domains\Legal\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Legal\Enums\ExtractionStatus;
use App\Domains\Legal\Enums\LegalReviewStatus;
use App\Domains\Legal\Enums\LegalVerificationStatus;
use Database\Factories\LegalDocumentVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property int $legal_document_id
 * @property string $version_label
 * @property string $content_checksum
 * @property string|null $storage_disk
 * @property string|null $storage_path
 * @property LegalReviewStatus $review_status
 * @property LegalVerificationStatus $verification_status
 * @property ExtractionStatus $extraction_status
 * @property Carbon|null $effective_from
 * @property Carbon|null $effective_until
 * @property Carbon|null $reviewed_at
 */
class LegalDocumentVersion extends Model
{
    /** @use HasFactory<LegalDocumentVersionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $hidden = ['storage_path', 'extracted_text'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'legal_document_id',
        'version_label',
        'source_url',
        'source_published_at',
        'effective_from',
        'effective_until',
        'retrieved_at',
        'retrieved_by',
        'content_checksum',
        'checksum_algorithm',
        'mime_type',
        'file_size',
        'storage_disk',
        'storage_path',
        'original_filename',
        'extracted_text',
        'extraction_status',
        'verification_status',
        'review_status',
        'reviewed_at',
        'reviewed_by',
        'change_summary',
        'supersedes_version_id',
        'content_version',
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
            'extraction_status' => ExtractionStatus::class,
            'verification_status' => LegalVerificationStatus::class,
            'review_status' => LegalReviewStatus::class,
            'reviewed_at' => 'datetime',
            'content_version' => 'integer',
            'file_size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->public_id ??= (string) Str::uuid();
        });
    }

    protected static function newFactory(): LegalDocumentVersionFactory
    {
        return LegalDocumentVersionFactory::new();
    }

    public function isApproved(): bool
    {
        return $this->review_status === LegalReviewStatus::Approved;
    }

    public function isLocked(): bool
    {
        return in_array($this->review_status, [
            LegalReviewStatus::Approved,
            LegalReviewStatus::Superseded,
            LegalReviewStatus::Archived,
        ], true);
    }

    /**
     * @return BelongsTo<LegalDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class, 'legal_document_id');
    }

    /**
     * @return HasMany<LegalProvision, $this>
     */
    public function provisions(): HasMany
    {
        return $this->hasMany(LegalProvision::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
