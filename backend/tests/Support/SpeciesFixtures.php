<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domains\Hunting\Enums\SpeciesContentStatus;
use App\Domains\Hunting\Enums\SpeciesPublicationStatus;
use App\Domains\Hunting\Enums\SpeciesVerificationStatus;
use App\Domains\Hunting\Models\KnowledgeCitation;
use App\Domains\Hunting\Models\KnowledgeSource;
use App\Domains\Hunting\Models\Species;
use App\Domains\Hunting\Models\SpeciesTranslation;
use Database\Seeders\HabitatVocabularySeeder;

final class SpeciesFixtures
{
    public static function seedHabitats(): void
    {
        (new HabitatVocabularySeeder)->run();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function draft(array $overrides = []): Species
    {
        self::seedHabitats();

        $species = Species::factory()->create($overrides);
        SpeciesTranslation::factory()->create([
            'species_id' => $species->id,
            'locale' => 'ka',
            'content_status' => SpeciesContentStatus::Draft,
        ]);

        return $species->fresh(['translations']) ?? $species;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function publishable(array $overrides = []): Species
    {
        self::seedHabitats();

        $species = Species::factory()->create([
            'verification_status' => SpeciesVerificationStatus::PartiallyVerified,
            'no_media_required' => true,
            ...$overrides,
        ]);

        SpeciesTranslation::factory()->published()->create([
            'species_id' => $species->id,
            'locale' => 'ka',
        ]);

        $source = KnowledgeSource::factory()->create();
        KnowledgeCitation::query()->create([
            'source_id' => $source->id,
            'citable_type' => 'species',
            'citable_id' => $species->id,
            'claim_key' => 'species',
        ]);

        return $species->fresh(['translations', 'citations']) ?? $species;
    }

    public static function published(): Species
    {
        $species = self::publishable([
            'publication_status' => SpeciesPublicationStatus::Published,
            'published_at' => now(),
            'reviewed_at' => now(),
        ]);

        SpeciesTranslation::factory()->english()->published()->create([
            'species_id' => $species->id,
        ]);

        return $species->fresh(['translations', 'citations']) ?? $species;
    }
}
