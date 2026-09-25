<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Models;

use App\Domains\Hunting\Enums\ActivityPattern;
use App\Domains\Hunting\Enums\MeasurementDepthUnit;
use App\Domains\Hunting\Enums\MeasurementLengthUnit;
use App\Domains\Hunting\Enums\MeasurementTemperatureUnit;
use App\Domains\Hunting\Enums\MeasurementWeightUnit;
use App\Domains\Hunting\Enums\MigrationPattern;
use App\Domains\Hunting\Enums\SocialBehavior;
use App\Domains\Hunting\Enums\WaterType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property MeasurementLengthUnit|null $length_unit
 * @property MeasurementWeightUnit|null $weight_unit
 * @property ActivityPattern|null $activity_pattern
 * @property SocialBehavior|null $social_behavior
 * @property MigrationPattern|null $migration_pattern
 * @property WaterType|null $water_type
 * @property MeasurementDepthUnit|null $depth_unit
 * @property MeasurementTemperatureUnit|null $temperature_unit
 */
class SpeciesCharacteristic extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'species_id',
        'average_length_min',
        'average_length_max',
        'length_unit',
        'average_weight_min',
        'average_weight_max',
        'weight_unit',
        'lifespan_years_min',
        'lifespan_years_max',
        'activity_pattern',
        'social_behavior',
        'migration_pattern',
        'water_type',
        'preferred_depth_min',
        'preferred_depth_max',
        'depth_unit',
        'temperature_range_min',
        'temperature_range_max',
        'temperature_unit',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'average_length_min' => 'decimal:2',
            'average_length_max' => 'decimal:2',
            'length_unit' => MeasurementLengthUnit::class,
            'average_weight_min' => 'decimal:2',
            'average_weight_max' => 'decimal:2',
            'weight_unit' => MeasurementWeightUnit::class,
            'lifespan_years_min' => 'decimal:2',
            'lifespan_years_max' => 'decimal:2',
            'activity_pattern' => ActivityPattern::class,
            'social_behavior' => SocialBehavior::class,
            'migration_pattern' => MigrationPattern::class,
            'water_type' => WaterType::class,
            'preferred_depth_min' => 'decimal:2',
            'preferred_depth_max' => 'decimal:2',
            'depth_unit' => MeasurementDepthUnit::class,
            'temperature_range_min' => 'decimal:2',
            'temperature_range_max' => 'decimal:2',
            'temperature_unit' => MeasurementTemperatureUnit::class,
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
     * @return MorphMany<KnowledgeCitation, $this>
     */
    public function citations(): MorphMany
    {
        return $this->morphMany(KnowledgeCitation::class, 'citable');
    }
}
