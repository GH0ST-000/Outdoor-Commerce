<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Hunting\Enums\SpeciesContentStatus;
use App\Domains\Hunting\Models\Species;
use App\Domains\Hunting\Models\SpeciesTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpeciesTranslation>
 */
class SpeciesTranslationFactory extends Factory
{
    protected $model = SpeciesTranslation::class;

    public function definition(): array
    {
        return [
            'species_id' => Species::factory(),
            'locale' => 'ka',
            'common_name' => 'სატესტო სახეობა',
            'short_name' => 'სატესტო',
            'summary' => 'სატესტო შეჯამება ველური ბუნების იდენტიფიკაციისთვის.',
            'identification' => 'სატესტო იდენტიფიკაციის ნიშნები.',
            'appearance' => null,
            'behavior' => null,
            'diet' => null,
            'habitat_description' => null,
            'breeding_notes' => null,
            'seasonal_behavior' => null,
            'field_notes' => null,
            'safety_notes' => null,
            'seo_title' => null,
            'seo_description' => null,
            'content_status' => SpeciesContentStatus::Draft,
        ];
    }

    public function english(): static
    {
        return $this->state(fn () => [
            'locale' => 'en',
            'common_name' => 'Test fixture species',
            'short_name' => 'Test species',
            'summary' => 'Test summary for wildlife identification.',
            'identification' => 'Test identification traits.',
        ]);
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'content_status' => SpeciesContentStatus::Published,
        ]);
    }
}
