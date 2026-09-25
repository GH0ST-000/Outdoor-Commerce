<?php

declare(strict_types=1);

namespace App\Domains\Legal\Models;

use App\Domains\Hunting\Models\Species;
use App\Domains\Legal\Enums\LegalActivityType;
use App\Domains\Legal\Enums\LegalRuleStatus;
use App\Domains\Legal\Enums\LegalVerificationLevel;
use App\Domains\Legal\Enums\SeasonBoundaryPrecision;
use App\Domains\Legal\Enums\SeasonScheduleType;
use App\Domains\Legal\Enums\SeasonType;
use Database\Factories\LegalSeasonDefinitionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Authoritative, source-backed season schedule. Occurrences are derived.
 *
 * @property int $id
 * @property string $public_id
 * @property int $legal_rule_id
 * @property int $species_id
 * @property LegalActivityType $activity_type
 * @property SeasonType $season_type
 * @property SeasonScheduleType $schedule_type
 * @property string $jurisdiction_code
 * @property string|null $region_code
 * @property string $timezone
 * @property SeasonBoundaryPrecision $boundary_precision
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property int|null $start_month
 * @property int|null $start_day
 * @property int|null $end_month
 * @property int|null $end_day
 * @property string|null $start_time
 * @property string|null $end_time
 * @property int|null $first_season_year
 * @property int|null $last_season_year
 * @property bool $crosses_calendar_year
 * @property LegalRuleStatus $status
 * @property LegalVerificationLevel $verification_level
 * @property int|null $generated_through_year
 * @property int $content_version
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $published_at
 * @property Carbon|null $updated_at
 */
class LegalSeasonDefinition extends Model
{
    /** @use HasFactory<LegalSeasonDefinitionFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $hidden = ['internal_notes'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'legal_rule_id',
        'species_id',
        'activity_type',
        'season_type',
        'schedule_type',
        'jurisdiction_code',
        'region_code',
        'zone_reference',
        'timezone',
        'boundary_precision',
        'start_date',
        'end_date',
        'start_month',
        'start_day',
        'end_month',
        'end_day',
        'start_time',
        'end_time',
        'first_season_year',
        'last_season_year',
        'crosses_calendar_year',
        'status',
        'verification_level',
        'generated_through_year',
        'content_version',
        'internal_notes',
        'created_by',
        'updated_by',
        'reviewed_by',
        'reviewed_at',
        'published_by',
        'published_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $definition): void {
            if ($definition->public_id === null || $definition->public_id === '') {
                $definition->public_id = (string) Str::uuid();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activity_type' => LegalActivityType::class,
            'season_type' => SeasonType::class,
            'schedule_type' => SeasonScheduleType::class,
            'boundary_precision' => SeasonBoundaryPrecision::class,
            'status' => LegalRuleStatus::class,
            'verification_level' => LegalVerificationLevel::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'crosses_calendar_year' => 'boolean',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<LegalRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(LegalRule::class, 'legal_rule_id');
    }

    /**
     * @return BelongsTo<Species, $this>
     */
    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class);
    }

    /**
     * @return HasMany<LegalSeasonOccurrence, $this>
     */
    public function occurrences(): HasMany
    {
        return $this->hasMany(LegalSeasonOccurrence::class, 'season_definition_id');
    }

    /**
     * @return HasMany<LegalSeasonOverride, $this>
     */
    public function overrides(): HasMany
    {
        return $this->hasMany(LegalSeasonOverride::class, 'base_season_definition_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', LegalRuleStatus::Published);
    }

    /**
     * @return array<string, mixed>
     */
    public function scheduleFingerprint(): array
    {
        return [
            'schedule_type' => $this->schedule_type->value,
            'boundary_precision' => $this->boundary_precision->value,
            'timezone' => $this->timezone,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'start_month' => $this->start_month,
            'start_day' => $this->start_day,
            'end_month' => $this->end_month,
            'end_day' => $this->end_day,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'first_season_year' => $this->first_season_year,
            'last_season_year' => $this->last_season_year,
            'crosses_calendar_year' => $this->crosses_calendar_year,
            'content_version' => $this->content_version,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    public function generationVersion(): string
    {
        return hash('sha256', (string) json_encode($this->scheduleFingerprint()));
    }

    protected static function newFactory(): LegalSeasonDefinitionFactory
    {
        return LegalSeasonDefinitionFactory::new();
    }
}
