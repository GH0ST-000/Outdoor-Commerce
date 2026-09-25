<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Hunting\Models\Species;
use App\Domains\Legal\Enums\LegalActivityType;
use App\Domains\Legal\Enums\LegalRuleStatus;
use App\Domains\Legal\Enums\LegalVerificationLevel;
use App\Domains\Legal\Enums\SeasonBoundaryPrecision;
use App\Domains\Legal\Enums\SeasonScheduleType;
use App\Domains\Legal\Enums\SeasonType;
use App\Domains\Legal\Models\LegalRule;
use App\Domains\Legal\Models\LegalSeasonDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Test-only fictional season. Never use in production seeds.
 *
 * @extends Factory<LegalSeasonDefinition>
 */
class LegalSeasonDefinitionFactory extends Factory
{
    protected $model = LegalSeasonDefinition::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::uuid(),
            'legal_rule_id' => LegalRule::factory(),
            'species_id' => Species::factory(),
            'activity_type' => LegalActivityType::Hunting,
            'season_type' => SeasonType::Opening,
            'schedule_type' => SeasonScheduleType::AnnualRecurring,
            'jurisdiction_code' => 'XX',
            'region_code' => null,
            'zone_reference' => null,
            'timezone' => 'Asia/Tbilisi',
            'boundary_precision' => SeasonBoundaryPrecision::Date,
            'start_date' => null,
            'end_date' => null,
            'start_month' => 9,
            'start_day' => 1,
            'end_month' => 11,
            'end_day' => 30,
            'start_time' => null,
            'end_time' => null,
            'first_season_year' => 2024,
            'last_season_year' => 2030,
            'crosses_calendar_year' => false,
            'status' => LegalRuleStatus::Draft,
            'verification_level' => LegalVerificationLevel::Unverified,
            'content_version' => 1,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => LegalRuleStatus::Published,
            'verification_level' => LegalVerificationLevel::LegallyReviewed,
            'published_at' => now(),
        ]);
    }

    public function crossYear(): static
    {
        return $this->state(fn (): array => [
            'start_month' => 11,
            'start_day' => 1,
            'end_month' => 1,
            'end_day' => 31,
            'crosses_calendar_year' => true,
        ]);
    }

    public function leapDay(): static
    {
        return $this->state(fn (): array => [
            'start_month' => 2,
            'start_day' => 29,
            'end_month' => 3,
            'end_day' => 15,
            'crosses_calendar_year' => false,
        ]);
    }
}
