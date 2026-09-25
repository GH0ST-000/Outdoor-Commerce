<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Queries;

use App\Domains\Hunting\Enums\SpeciesActivityType;
use App\Domains\Hunting\Enums\SpeciesDomainType;
use App\Domains\Hunting\Models\Habitat;
use App\Domains\Hunting\Models\Species;
use App\Domains\Hunting\Models\SpeciesConservationAssessment;
use App\Domains\Hunting\Support\SpeciesLocales;

final class PublicSpeciesFiltersQuery
{
    /**
     * @return array<string, mixed>
     */
    public function all(string $locale): array
    {
        $locale = SpeciesLocales::isSupported($locale) ? $locale : SpeciesLocales::default();

        $habitats = Habitat::query()
            ->where('is_active', true)
            ->with('translations')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Habitat $habitat): array => [
                'code' => $habitat->code,
                'name' => $habitat->localizedName($locale),
            ])
            ->all();

        $taxonomy = Species::query()
            ->published()
            ->select(['class_name', 'order_name', 'family'])
            ->get();

        $conservation = SpeciesConservationAssessment::query()
            ->whereIn('species_id', Species::query()->published()->select('id'))
            ->select(['status_code', 'assessment_scope', 'assessment_system'])
            ->distinct()
            ->get()
            ->map(static fn (SpeciesConservationAssessment $row): array => [
                'status_code' => $row->status_code,
                'assessment_scope' => $row->assessment_scope->value,
                'assessment_system' => $row->assessment_system,
            ])
            ->all();

        return [
            'activity_types' => array_map(
                static fn ($case) => $case->value,
                SpeciesActivityType::cases(),
            ),
            'domain_types' => array_map(
                static fn ($case) => $case->value,
                SpeciesDomainType::cases(),
            ),
            'habitats' => $habitats,
            'taxonomy' => [
                'classes' => $taxonomy->pluck('class_name')->filter()->unique()->values()->all(),
                'orders' => $taxonomy->pluck('order_name')->filter()->unique()->values()->all(),
                'families' => $taxonomy->pluck('family')->filter()->unique()->values()->all(),
            ],
            'conservation' => $conservation,
            'sorts' => ['name', 'newest', 'scientific'],
        ];
    }
}
