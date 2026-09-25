<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Legal\Enums\LegalActivityType;
use App\Domains\Legal\Enums\LegalRuleEffect;
use App\Domains\Legal\Enums\LegalRuleStatus;
use App\Domains\Legal\Enums\LegalRuleType;
use App\Domains\Legal\Enums\LegalVerificationLevel;
use App\Domains\Legal\Models\LegalRule;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LegalRule>
 */
class LegalRuleFactory extends Factory
{
    protected $model = LegalRule::class;

    public function definition(): array
    {
        $title = 'FICTIONAL test rule '.$this->faker->unique()->numerify('###');

        return [
            'public_id' => (string) Str::uuid(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'activity_type' => LegalActivityType::Hunting,
            'rule_type' => LegalRuleType::Prohibition,
            'effect' => LegalRuleEffect::Prohibit,
            'jurisdiction_code' => 'GE',
            'effective_from' => now()->subYear(),
            'effective_until' => null,
            'priority' => 100,
            'status' => LegalRuleStatus::Draft,
            'verification_level' => LegalVerificationLevel::Unverified,
            'interpretation_summary' => 'Fictional interpretation for tests. This is not legal advice.',
            'content_version' => 1,
        ];
    }
}
