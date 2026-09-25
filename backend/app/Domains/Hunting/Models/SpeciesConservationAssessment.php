<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Models;

use App\Domains\Hunting\Enums\ConservationAssessmentScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property ConservationAssessmentScope $assessment_scope
 * @property Carbon|null $assessed_at
 * @property KnowledgeSource|null $source
 */
class SpeciesConservationAssessment extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'species_id',
        'assessment_system',
        'status_code',
        'assessment_scope',
        'assessed_at',
        'source_id',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assessment_scope' => ConservationAssessmentScope::class,
            'assessed_at' => 'date',
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
     * @return BelongsTo<KnowledgeSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSource::class, 'source_id');
    }
}
