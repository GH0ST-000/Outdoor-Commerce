<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Geography\Enums\SpatialAssignmentStatus;
use App\Domains\Geography\Enums\SpatialAssignmentType;
use App\Domains\Geography\Models\LegalRuleSpatialZone;
use App\Domains\Geography\Models\SpatialZone;
use App\Domains\Legal\Models\LegalRule;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Test-only fictional assignment. Never use in production seeds.
 *
 * @extends Factory<LegalRuleSpatialZone>
 */
class LegalRuleSpatialZoneFactory extends Factory
{
    protected $model = LegalRuleSpatialZone::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::uuid(),
            'legal_rule_id' => LegalRule::factory(),
            'spatial_zone_id' => SpatialZone::factory(),
            'assignment_type' => SpatialAssignmentType::ProhibitedWithin,
            'precedence' => 0,
            'effective_from' => now()->subYear(),
            'status' => SpatialAssignmentStatus::Draft,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => SpatialAssignmentStatus::Published,
            'reviewed_at' => now(),
        ]);
    }
}
