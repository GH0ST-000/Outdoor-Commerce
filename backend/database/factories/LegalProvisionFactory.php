<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Legal\Enums\LegalReviewStatus;
use App\Domains\Legal\Enums\ProvisionType;
use App\Domains\Legal\Models\LegalDocumentVersion;
use App\Domains\Legal\Models\LegalProvision;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LegalProvision>
 */
class LegalProvisionFactory extends Factory
{
    protected $model = LegalProvision::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::uuid(),
            'legal_document_version_id' => LegalDocumentVersion::factory(),
            'provision_type' => ProvisionType::Article,
            'reference_code' => 'Art. '.$this->faker->unique()->numberBetween(1, 99),
            'heading' => 'Fictional test article',
            'official_text' => 'FICTIONAL official text for tests only.',
            'normalized_summary' => 'Editorial summary, not official text.',
            'sort_order' => 1,
            'review_status' => LegalReviewStatus::Draft,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'review_status' => LegalReviewStatus::Approved,
            'reviewed_at' => now(),
        ]);
    }
}
