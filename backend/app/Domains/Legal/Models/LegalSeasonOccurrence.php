<?php

declare(strict_types=1);

namespace App\Domains\Legal\Models;

use App\Domains\Hunting\Models\Species;
use App\Domains\Legal\Enums\LegalActivityType;
use App\Domains\Legal\Enums\LegalRuleEffect;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Derived calendar projection. Safe to regenerate. Not independently editable.
 *
 * @property int $id
 * @property int $season_definition_id
 * @property int $species_id
 * @property LegalActivityType $activity_type
 * @property int $season_year
 * @property Carbon $starts_at
 * @property Carbon $ends_at_exclusive
 * @property Carbon $local_start_date
 * @property Carbon $local_end_date_inclusive
 * @property LegalRuleEffect $effect
 * @property bool $is_current
 * @property string $generation_version
 * @property Carbon|null $definition_updated_at
 * @property Carbon|null $updated_at
 */
class LegalSeasonOccurrence extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'season_definition_id',
        'species_id',
        'activity_type',
        'season_year',
        'jurisdiction_code',
        'region_code',
        'zone_reference',
        'starts_at',
        'ends_at_exclusive',
        'local_start_date',
        'local_end_date_inclusive',
        'effect',
        'generation_version',
        'definition_updated_at',
        'is_current',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activity_type' => LegalActivityType::class,
            'effect' => LegalRuleEffect::class,
            'starts_at' => 'datetime',
            'ends_at_exclusive' => 'datetime',
            'local_start_date' => 'date',
            'local_end_date_inclusive' => 'date',
            'definition_updated_at' => 'datetime',
            'is_current' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<LegalSeasonDefinition, $this>
     */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(LegalSeasonDefinition::class, 'season_definition_id');
    }

    /**
     * @return BelongsTo<Species, $this>
     */
    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    /**
     * Half-open overlap: starts_at < queryEnd AND ends_at_exclusive > queryStart.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOverlapping(Builder $query, \DateTimeInterface $startsAt, \DateTimeInterface $endsAtExclusive): Builder
    {
        return $query
            ->where('starts_at', '<', $endsAtExclusive)
            ->where('ends_at_exclusive', '>', $startsAt);
    }
}
