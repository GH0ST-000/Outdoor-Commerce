<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Models;

use App\Domains\Hunting\Enums\KnowledgeSourceType;
use App\Domains\Hunting\Enums\SpeciesVerificationStatus;
use App\Models\User;
use Database\Factories\KnowledgeSourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Reusable provenance record for species facts and, later, legal/geographic claims.
 *
 * @property int $id
 * @property string $public_id
 * @property string $title
 * @property string $publisher
 * @property KnowledgeSourceType $source_type
 * @property string|null $url
 * @property string|null $document_identifier
 * @property string|null $language
 * @property Carbon|null $published_at
 * @property Carbon|null $effective_from
 * @property Carbon|null $effective_to
 * @property Carbon|null $retrieved_at
 * @property bool $is_official
 * @property SpeciesVerificationStatus $verification_status
 * @property string|null $checksum
 * @property string|null $archived_local_path
 * @property string|null $notes
 * @property int $source_version
 */
class KnowledgeSource extends Model
{
    /** @use HasFactory<KnowledgeSourceFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $hidden = [
        'archived_local_path',
        'notes',
        'checksum',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'title',
        'publisher',
        'source_type',
        'url',
        'document_identifier',
        'language',
        'published_at',
        'effective_from',
        'effective_to',
        'retrieved_at',
        'is_official',
        'verification_status',
        'checksum',
        'archived_local_path',
        'notes',
        'source_version',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_type' => KnowledgeSourceType::class,
            'published_at' => 'date',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'retrieved_at' => 'date',
            'is_official' => 'boolean',
            'verification_status' => SpeciesVerificationStatus::class,
            'source_version' => 'integer',
        ];
    }

    /**
     * @return HasMany<KnowledgeCitation, $this>
     */
    public function citations(): HasMany
    {
        return $this->hasMany(KnowledgeCitation::class, 'source_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function newFactory(): KnowledgeSourceFactory
    {
        return KnowledgeSourceFactory::new();
    }

    public static function resolveKey(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            $id = (int) $value;

            return self::query()->whereKey($id)->exists() ? $id : null;
        }

        if (! is_string($value)) {
            return null;
        }

        $id = self::query()->where('public_id', $value)->value('id');

        return is_numeric($id) ? (int) $id : null;
    }
}
