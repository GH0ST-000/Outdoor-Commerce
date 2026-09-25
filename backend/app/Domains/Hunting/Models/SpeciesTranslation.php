<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Models;

use App\Domains\Hunting\Enums\SpeciesContentStatus;
use Database\Factories\SpeciesTranslationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $species_id
 * @property string $locale
 * @property string $common_name
 * @property string|null $short_name
 * @property string $summary
 * @property string $identification
 * @property string|null $appearance
 * @property string|null $behavior
 * @property string|null $diet
 * @property string|null $habitat_description
 * @property string|null $breeding_notes
 * @property string|null $seasonal_behavior
 * @property string|null $field_notes
 * @property string|null $safety_notes
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property SpeciesContentStatus $content_status
 */
class SpeciesTranslation extends Model
{
    /** @use HasFactory<SpeciesTranslationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'species_id',
        'locale',
        'common_name',
        'short_name',
        'summary',
        'identification',
        'appearance',
        'behavior',
        'diet',
        'habitat_description',
        'breeding_notes',
        'seasonal_behavior',
        'field_notes',
        'safety_notes',
        'seo_title',
        'seo_description',
        'content_status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content_status' => SpeciesContentStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Species, $this>
     */
    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class);
    }

    public function isPublished(): bool
    {
        return $this->content_status === SpeciesContentStatus::Published;
    }

    protected static function newFactory(): SpeciesTranslationFactory
    {
        return SpeciesTranslationFactory::new();
    }
}
