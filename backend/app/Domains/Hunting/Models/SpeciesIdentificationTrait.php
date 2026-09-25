<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Models;

use App\Domains\Hunting\Enums\IdentificationTraitCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property IdentificationTraitCategory $category
 */
class SpeciesIdentificationTrait extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'species_id',
        'locale',
        'category',
        'label',
        'description',
        'sort_order',
        'source_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => IdentificationTraitCategory::class,
            'sort_order' => 'integer',
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
