<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Models;

use App\Domains\Catalog\Models\MediaAttachment;
use App\Domains\Hunting\Enums\NativeStatus;
use App\Domains\Hunting\Enums\SpeciesActivityType;
use App\Domains\Hunting\Enums\SpeciesDomainType;
use App\Domains\Hunting\Enums\SpeciesPublicationStatus;
use App\Domains\Hunting\Enums\SpeciesVerificationStatus;
use App\Domains\Hunting\Enums\TaxonomicRank;
use App\Models\User;
use Database\Factories\SpeciesFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Biological species record. Activity/domain types are navigation labels only
 * and must never be treated as hunting or fishing permission.
 *
 * @property int $id
 * @property string $public_id
 * @property string $canonical_slug
 * @property string $scientific_name
 * @property string $scientific_name_normalized
 * @property string|null $scientific_name_authorship
 * @property TaxonomicRank $taxonomic_rank
 * @property string $kingdom
 * @property string|null $phylum
 * @property string|null $class_name
 * @property string|null $order_name
 * @property string|null $family
 * @property string|null $genus
 * @property string|null $species_epithet
 * @property SpeciesDomainType $domain_type
 * @property SpeciesActivityType $activity_type
 * @property NativeStatus|null $native_status
 * @property SpeciesVerificationStatus $verification_status
 * @property SpeciesPublicationStatus $publication_status
 * @property int $content_version
 * @property bool $no_media_required
 * @property Carbon|null $published_at
 * @property Carbon|null $reviewed_at
 * @property int|null $reviewed_by
 * @property Carbon|null $archived_at
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class Species extends Model
{
    /** @use HasFactory<SpeciesFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'canonical_slug',
        'scientific_name',
        'scientific_name_normalized',
        'scientific_name_authorship',
        'taxonomic_rank',
        'kingdom',
        'phylum',
        'class_name',
        'order_name',
        'family',
        'genus',
        'species_epithet',
        'domain_type',
        'activity_type',
        'native_status',
        'verification_status',
        'publication_status',
        'content_version',
        'no_media_required',
        'published_at',
        'reviewed_at',
        'reviewed_by',
        'archived_at',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'taxonomic_rank' => TaxonomicRank::class,
            'domain_type' => SpeciesDomainType::class,
            'activity_type' => SpeciesActivityType::class,
            'native_status' => NativeStatus::class,
            'verification_status' => SpeciesVerificationStatus::class,
            'publication_status' => SpeciesPublicationStatus::class,
            'content_version' => 'integer',
            'no_media_required' => 'boolean',
            'published_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<SpeciesTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(SpeciesTranslation::class);
    }

    /**
     * @return HasMany<SpeciesAlias, $this>
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(SpeciesAlias::class);
    }

    /**
     * @return HasOne<SpeciesCharacteristic, $this>
     */
    public function characteristic(): HasOne
    {
        return $this->hasOne(SpeciesCharacteristic::class);
    }

    /**
     * @return HasMany<SpeciesHabitat, $this>
     */
    public function habitatLinks(): HasMany
    {
        return $this->hasMany(SpeciesHabitat::class);
    }

    /**
     * @return HasMany<SpeciesIdentificationTrait, $this>
     */
    public function identificationTraits(): HasMany
    {
        return $this->hasMany(SpeciesIdentificationTrait::class);
    }

    /**
     * @return HasMany<SpeciesSimilar, $this>
     */
    public function similarFrom(): HasMany
    {
        return $this->hasMany(SpeciesSimilar::class, 'species_id');
    }

    /**
     * @return HasMany<SpeciesSimilar, $this>
     */
    public function similarTo(): HasMany
    {
        return $this->hasMany(SpeciesSimilar::class, 'similar_species_id');
    }

    /**
     * @return HasMany<SpeciesConservationAssessment, $this>
     */
    public function conservationAssessments(): HasMany
    {
        return $this->hasMany(SpeciesConservationAssessment::class);
    }

    /**
     * @return HasMany<SpeciesRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(SpeciesRevision::class);
    }

    /**
     * @return MorphMany<MediaAttachment, $this>
     */
    public function mediaAttachments(): MorphMany
    {
        return $this->morphMany(MediaAttachment::class, 'mediable');
    }

    /**
     * @return MorphMany<KnowledgeCitation, $this>
     */
    public function citations(): MorphMany
    {
        return $this->morphMany(KnowledgeCitation::class, 'citable');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<Species>  $query
     * @return Builder<Species>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('publication_status', SpeciesPublicationStatus::Published->value)
            ->whereNotNull('published_at');
    }

    public function isPublished(): bool
    {
        return $this->publication_status === SpeciesPublicationStatus::Published
            && $this->published_at !== null;
    }

    public function translation(string $locale): ?SpeciesTranslation
    {
        $translations = $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->get();

        return $translations->firstWhere('locale', $locale);
    }

    protected static function newFactory(): SpeciesFactory
    {
        return SpeciesFactory::new();
    }
}
