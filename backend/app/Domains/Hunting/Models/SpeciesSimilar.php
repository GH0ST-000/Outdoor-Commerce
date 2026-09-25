<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Models;

use App\Domains\Hunting\Enums\SimilarSpeciesRelationType;
use App\Domains\Hunting\Enums\SpeciesVerificationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property SimilarSpeciesRelationType $relationship_type
 * @property SpeciesVerificationStatus $confidence
 * @property Species|null $similarSpecies
 */
class SpeciesSimilar extends Model
{
    protected $table = 'species_similar';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'species_id',
        'similar_species_id',
        'relationship_type',
        'confidence',
        'notes',
        'source_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'relationship_type' => SimilarSpeciesRelationType::class,
            'confidence' => SpeciesVerificationStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Species, $this>
     */
    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class);
    }

    /**
     * @return BelongsTo<Species, $this>
     */
    public function similarSpecies(): BelongsTo
    {
        return $this->belongsTo(Species::class, 'similar_species_id');
    }

    /**
     * @return BelongsTo<KnowledgeSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSource::class, 'source_id');
    }
}
