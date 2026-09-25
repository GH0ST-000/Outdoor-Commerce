<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Services;

use App\Domains\Hunting\Models\Species;
use App\Domains\Hunting\Models\SpeciesAlias;
use App\Domains\Hunting\Models\SpeciesConservationAssessment;
use App\Domains\Hunting\Models\SpeciesHabitat;
use App\Domains\Hunting\Models\SpeciesIdentificationTrait;
use App\Domains\Hunting\Models\SpeciesRevision;
use App\Domains\Hunting\Models\SpeciesSimilar;
use App\Domains\Hunting\Models\SpeciesTranslation;
use App\Domains\Identity\Models\User;

final class SpeciesRevisionService
{
    public function record(Species $species, User $actor, string $summary): SpeciesRevision
    {
        $species->loadMissing([
            'translations',
            'aliases',
            'characteristic',
            'habitatLinks',
            'identificationTraits',
            'similarFrom',
            'conservationAssessments',
            'citations.source',
        ]);

        $next = (int) $species->revisions()->max('revision_number') + 1;

        return SpeciesRevision::query()->create([
            'species_id' => $species->id,
            'revision_number' => $next,
            'actor_id' => $actor->id,
            'change_summary' => mb_substr(trim($summary), 0, 240),
            'snapshot' => $this->snapshot($species),
            'created_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(Species $species): array
    {
        return [
            'public_id' => $species->public_id,
            'canonical_slug' => $species->canonical_slug,
            'scientific_name' => $species->scientific_name,
            'scientific_name_authorship' => $species->scientific_name_authorship,
            'taxonomic_rank' => $species->taxonomic_rank->value,
            'kingdom' => $species->kingdom,
            'phylum' => $species->phylum,
            'class_name' => $species->class_name,
            'order_name' => $species->order_name,
            'family' => $species->family,
            'genus' => $species->genus,
            'species_epithet' => $species->species_epithet,
            'domain_type' => $species->domain_type->value,
            'activity_type' => $species->activity_type->value,
            'native_status' => $species->native_status?->value,
            'verification_status' => $species->verification_status->value,
            'publication_status' => $species->publication_status->value,
            'content_version' => $species->content_version,
            'no_media_required' => $species->no_media_required,
            'translations' => $species->translations->map(static fn (SpeciesTranslation $row): array => [
                'locale' => $row->locale,
                'common_name' => $row->common_name,
                'short_name' => $row->short_name,
                'summary' => $row->summary,
                'identification' => $row->identification,
                'appearance' => $row->appearance,
                'behavior' => $row->behavior,
                'diet' => $row->diet,
                'habitat_description' => $row->habitat_description,
                'breeding_notes' => $row->breeding_notes,
                'seasonal_behavior' => $row->seasonal_behavior,
                'field_notes' => $row->field_notes,
                'safety_notes' => $row->safety_notes,
                'seo_title' => $row->seo_title,
                'seo_description' => $row->seo_description,
                'content_status' => $row->content_status->value,
            ])->all(),
            'aliases' => $species->aliases->map(static fn (SpeciesAlias $row): array => [
                'locale' => $row->locale,
                'name' => $row->name,
                'type' => $row->type->value,
                'is_searchable' => $row->is_searchable,
                'is_public' => $row->is_public,
            ])->all(),
            'habitats' => $species->habitatLinks->map(static fn (SpeciesHabitat $row): array => [
                'habitat_id' => $row->habitat_id,
                'importance' => $row->importance->value,
            ])->all(),
            'traits' => $species->identificationTraits->map(static fn (SpeciesIdentificationTrait $row): array => [
                'locale' => $row->locale,
                'category' => $row->category->value,
                'label' => $row->label,
                'description' => $row->description,
                'sort_order' => $row->sort_order,
            ])->all(),
            'similar' => $species->similarFrom->map(static fn (SpeciesSimilar $row): array => [
                'similar_species_id' => $row->similar_species_id,
                'relationship_type' => $row->relationship_type->value,
                'confidence' => $row->confidence->value,
            ])->all(),
            'conservation' => $species->conservationAssessments->map(static fn (SpeciesConservationAssessment $row): array => [
                'assessment_system' => $row->assessment_system,
                'status_code' => $row->status_code,
                'assessment_scope' => $row->assessment_scope->value,
                'assessed_at' => $row->assessed_at?->toDateString(),
            ])->all(),
            'citation_source_ids' => $species->citations->pluck('source_id')->all(),
        ];
    }
}
