<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Legal\Enums\LegalRuleStatus;
use App\Domains\Legal\Enums\LegalVerificationLevel;
use App\Domains\Legal\Enums\SeasonOverrideType;
use App\Domains\Legal\Models\LegalRule;
use App\Domains\Legal\Models\LegalSeasonDefinition;
use App\Domains\Legal\Models\LegalSeasonOverride;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Test-only fictional override. Never use in production seeds.
 *
 * @extends Factory<LegalSeasonOverride>
 */
class LegalSeasonOverrideFactory extends Factory
{
    protected $model = LegalSeasonOverride::class;

    public function definition(): array
    {
        $start = now()->setTimezone('Asia/Tbilisi')->startOfDay();

        return [
            'public_id' => (string) Str::uuid(),
            'base_season_definition_id' => LegalSeasonDefinition::factory(),
            'legal_rule_id' => LegalRule::factory(),
            'override_type' => SeasonOverrideType::Closure,
            'starts_at' => $start,
            'ends_at_exclusive' => $start->copy()->addDays(7),
            'jurisdiction_code' => 'XX',
            'region_code' => null,
            'zone_reference' => null,
            'reason' => 'FICTIONAL test closure',
            'precedence' => 100,
            'status' => LegalRuleStatus::Draft,
            'verification_level' => LegalVerificationLevel::Unverified,
        ];
    }
}
