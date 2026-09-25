<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Models;

use App\Domains\Hunting\Enums\HabitatImportance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property HabitatImportance $importance
 */
class SpeciesHabitat extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'species_id',
        'habitat_id',
        'importance',
        'notes',
        'source_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'importance' => HabitatImportance::class,
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
     * @return BelongsTo<Habitat, $this>
     */
    public function habitat(): BelongsTo
    {
        return $this->belongsTo(Habitat::class);
    }

    /**
     * @return BelongsTo<KnowledgeSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSource::class, 'source_id');
    }
}
