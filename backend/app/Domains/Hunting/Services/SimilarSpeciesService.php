<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Services;

use App\Domains\Hunting\Enums\SimilarSpeciesRelationType;
use App\Domains\Hunting\Enums\SpeciesVerificationStatus;
use App\Domains\Hunting\Exceptions\SpeciesException;
use App\Domains\Hunting\Models\KnowledgeSource;
use App\Domains\Hunting\Models\Species;
use App\Domains\Hunting\Models\SpeciesSimilar;
use App\Domains\Hunting\Support\SpeciesHtmlSanitizer;

final class SimilarSpeciesService
{
    public function __construct(private readonly SpeciesHtmlSanitizer $sanitizer) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(Species $species, array $input): SpeciesSimilar
    {
        $otherPublicId = (string) ($input['similar_species_id'] ?? '');
        $other = Species::query()->where('public_id', $otherPublicId)->first();
        if ($other === null) {
            throw SpeciesException::similarRelationInvalid('Related species was not found.');
        }

        if ($other->id === $species->id) {
            throw SpeciesException::similarRelationInvalid('A species cannot be similar to itself.');
        }

        $type = SimilarSpeciesRelationType::from(
            (string) ($input['relationship_type'] ?? SimilarSpeciesRelationType::VisuallySimilar->value),
        );

        $duplicate = SpeciesSimilar::query()
            ->where('relationship_type', $type->value)
            ->where(function ($query) use ($species, $other): void {
                $query->where(function ($inner) use ($species, $other): void {
                    $inner->where('species_id', $species->id)->where('similar_species_id', $other->id);
                })->orWhere(function ($inner) use ($species, $other): void {
                    $inner->where('species_id', $other->id)->where('similar_species_id', $species->id);
                });
            })
            ->exists();

        if ($duplicate) {
            throw SpeciesException::similarRelationInvalid('This similar-species relationship already exists.');
        }

        $notes = isset($input['notes']) ? $this->sanitizer->sanitize((string) $input['notes']) : null;
        $sourceId = KnowledgeSource::resolveKey($input['source_id'] ?? $input['source_public_id'] ?? null);

        return SpeciesSimilar::query()->create([
            'species_id' => $species->id,
            'similar_species_id' => $other->id,
            'relationship_type' => $type,
            'confidence' => SpeciesVerificationStatus::from(
                (string) ($input['confidence'] ?? SpeciesVerificationStatus::Unverified->value),
            ),
            'notes' => $notes,
            'source_id' => $sourceId,
        ]);
    }
}
