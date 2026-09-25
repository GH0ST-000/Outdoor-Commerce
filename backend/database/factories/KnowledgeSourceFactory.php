<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Hunting\Enums\KnowledgeSourceType;
use App\Domains\Hunting\Enums\SpeciesVerificationStatus;
use App\Domains\Hunting\Models\KnowledgeSource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KnowledgeSource>
 */
class KnowledgeSourceFactory extends Factory
{
    protected $model = KnowledgeSource::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::uuid(),
            'title' => 'Test scientific source',
            'publisher' => 'Test publisher',
            'source_type' => KnowledgeSourceType::ScientificDatabase,
            'url' => 'https://example.org/source',
            'document_identifier' => null,
            'language' => 'en',
            'published_at' => now()->toDateString(),
            'effective_from' => null,
            'effective_to' => null,
            'retrieved_at' => now()->toDateString(),
            'is_official' => false,
            'verification_status' => SpeciesVerificationStatus::PartiallyVerified,
            'checksum' => null,
            'archived_local_path' => null,
            'notes' => null,
            'source_version' => 1,
        ];
    }

    public function official(): static
    {
        return $this->state(fn () => [
            'source_type' => KnowledgeSourceType::Government,
            'is_official' => true,
        ]);
    }
}
