<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Models;

use App\Domains\Hunting\Enums\SpeciesAliasType;
use Database\Factories\SpeciesAliasFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $species_id
 * @property string|null $locale
 * @property string $name
 * @property string $normalized_name
 * @property SpeciesAliasType $type
 * @property bool $is_searchable
 * @property bool $is_public
 * @property int|null $source_id
 */
class SpeciesAlias extends Model
{
    /** @use HasFactory<SpeciesAliasFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'species_id',
        'locale',
        'name',
        'normalized_name',
        'type',
        'is_searchable',
        'is_public',
        'source_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SpeciesAliasType::class,
            'is_searchable' => 'boolean',
            'is_public' => 'boolean',
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

    protected static function newFactory(): SpeciesAliasFactory
    {
        return SpeciesAliasFactory::new();
    }
}
