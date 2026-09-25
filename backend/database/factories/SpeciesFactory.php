<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Hunting\Enums\SpeciesActivityType;
use App\Domains\Hunting\Enums\SpeciesDomainType;
use App\Domains\Hunting\Enums\SpeciesPublicationStatus;
use App\Domains\Hunting\Enums\SpeciesVerificationStatus;
use App\Domains\Hunting\Enums\TaxonomicRank;
use App\Domains\Hunting\Models\Species;
use App\Domains\Hunting\Support\ScientificNameNormalizer;
use App\Domains\Hunting\Support\SpeciesSlug;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Species>
 */
class SpeciesFactory extends Factory
{
    protected $model = Species::class;

    public function definition(): array
    {
        $scientific = 'Testus '.$this->faker->unique()->lexify('????????');
        $display = ScientificNameNormalizer::display($scientific);

        return [
            'public_id' => (string) Str::uuid(),
            'canonical_slug' => SpeciesSlug::fromScientificName($display).'-'.Str::lower(Str::random(4)),
            'scientific_name' => $display,
            'scientific_name_normalized' => ScientificNameNormalizer::normalize($display),
            'scientific_name_authorship' => null,
            'taxonomic_rank' => TaxonomicRank::Species,
            'kingdom' => 'Animalia',
            'phylum' => 'Chordata',
            'class_name' => 'Mammalia',
            'order_name' => 'Testiformes',
            'family' => 'Testidae',
            'genus' => ScientificNameNormalizer::genus($display),
            'species_epithet' => ScientificNameNormalizer::epithet($display),
            'domain_type' => SpeciesDomainType::Terrestrial,
            'activity_type' => SpeciesActivityType::Wildlife,
            'native_status' => null,
            'verification_status' => SpeciesVerificationStatus::Unverified,
            'publication_status' => SpeciesPublicationStatus::Draft,
            'content_version' => 1,
            'no_media_required' => false,
            'published_at' => null,
            'reviewed_at' => null,
            'reviewed_by' => null,
            'archived_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'publication_status' => SpeciesPublicationStatus::Published,
            'verification_status' => SpeciesVerificationStatus::PartiallyVerified,
            'published_at' => now(),
            'reviewed_at' => now(),
            'no_media_required' => true,
        ]);
    }
}
