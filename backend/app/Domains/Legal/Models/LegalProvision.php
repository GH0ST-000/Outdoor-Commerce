<?php

declare(strict_types=1);

namespace App\Domains\Legal\Models;

use App\Domains\Legal\Enums\LegalReviewStatus;
use App\Domains\Legal\Enums\ProvisionType;
use Database\Factories\LegalProvisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property int $legal_document_version_id
 * @property int|null $parent_provision_id
 * @property ProvisionType $provision_type
 * @property string $reference_code
 * @property string $official_text
 * @property string|null $normalized_summary
 * @property LegalReviewStatus $review_status
 * @property Carbon|null $reviewed_at
 */
class LegalProvision extends Model
{
    /** @use HasFactory<LegalProvisionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'legal_document_version_id',
        'parent_provision_id',
        'provision_type',
        'reference_code',
        'heading',
        'official_text',
        'normalized_summary',
        'sort_order',
        'effective_from',
        'effective_until',
        'review_status',
        'reviewed_at',
        'reviewed_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provision_type' => ProvisionType::class,
            'review_status' => LegalReviewStatus::class,
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
            'reviewed_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->public_id ??= (string) Str::uuid();
        });
    }

    protected static function newFactory(): LegalProvisionFactory
    {
        return LegalProvisionFactory::new();
    }

    public function isApproved(): bool
    {
        return $this->review_status === LegalReviewStatus::Approved;
    }

    /**
     * @return BelongsTo<LegalDocumentVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(LegalDocumentVersion::class, 'legal_document_version_id');
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_provision_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_provision_id');
    }

    /**
     * @return HasMany<LegalRuleCitation, $this>
     */
    public function citations(): HasMany
    {
        return $this->hasMany(LegalRuleCitation::class);
    }
}
