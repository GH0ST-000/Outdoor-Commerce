<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Services;

use App\Domains\Catalog\Enums\MediaFormat;
use App\Domains\Catalog\Enums\MediaPreset;
use App\Domains\Hunting\Enums\SpeciesContentStatus;
use App\Domains\Hunting\Models\Species;
use App\Domains\Hunting\Models\SpeciesAlias;
use App\Domains\Hunting\Models\SpeciesConservationAssessment;
use App\Domains\Hunting\Models\SpeciesHabitat;
use App\Domains\Hunting\Models\SpeciesTranslation;
use App\Domains\Hunting\Support\SpeciesLocales;
use App\Domains\Hunting\Support\SpeciesMediaUrl;

final class SpeciesSearchDocumentFactory
{
    public function __construct(private readonly SpeciesTranslationResolver $translations) {}

    public function id(Species $species, string $locale): string
    {
        return $species->public_id.'_'.$locale;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function make(Species $species, string $locale): ?array
    {
        $species->loadMissing([
            'translations',
            'aliases',
            'habitatLinks.habitat',
            'conservationAssessments',
            'mediaAttachments.asset.derivatives',
            'mediaAttachments.translations',
        ]);

        $translation = $species->translations
            ->first(static fn (SpeciesTranslation $row): bool => $row->locale === $locale && $row->content_status === SpeciesContentStatus::Published);

        if ($translation === null && $locale !== SpeciesLocales::default()) {
            return null;
        }

        if ($translation === null) {
            $translation = $this->translations->resolve($species, $locale, true);
        }

        if ($translation === null) {
            return null;
        }

        $publicAliases = $species->aliases
            ->filter(static fn (SpeciesAlias $alias): bool => $alias->is_public && ($alias->locale === null || $alias->locale === $locale))
            ->pluck('name')
            ->values()
            ->all();

        $searchAliases = $species->aliases
            ->filter(static fn (SpeciesAlias $alias): bool => $alias->is_searchable && ($alias->locale === null || $alias->locale === $locale))
            ->pluck('name')
            ->values()
            ->all();

        $primary = $species->mediaAttachments->firstWhere('is_primary', true);
        $thumb = $primary?->asset?->derivative(MediaPreset::Card, MediaFormat::Webp)
            ?? $primary?->asset?->derivatives->first();

        return [
            'id' => $this->id($species, $locale),
            'slug' => $species->canonical_slug,
            'locale' => $locale,
            'common_name' => $translation->common_name,
            'scientific_name' => $species->scientific_name,
            'public_aliases' => $publicAliases,
            'search_aliases' => $searchAliases,
            'taxonomy_codes' => array_values(array_filter([
                $species->kingdom,
                $species->class_name,
                $species->order_name,
                $species->family,
                $species->genus,
            ])),
            'taxonomy_names' => array_values(array_filter([
                $species->kingdom,
                $species->class_name,
                $species->order_name,
                $species->family,
                $species->genus,
                $species->scientific_name,
            ])),
            'activity_type' => $species->activity_type->value,
            'domain_type' => $species->domain_type->value,
            'habitat_codes' => $species->habitatLinks->map(static fn (SpeciesHabitat $link): string => $link->habitat->code)->filter()->values()->all(),
            'conservation_codes' => $species->conservationAssessments->map(
                static fn (SpeciesConservationAssessment $row): string => $row->assessment_scope->value.':'.$row->status_code,
            )->all(),
            'summary' => $translation->summary,
            'primary_media' => SpeciesMediaUrl::forDerivative($thumb),
            'published_timestamp' => $species->published_at?->getTimestamp() ?? 0,
            'content_version' => $species->content_version,
        ];
    }
}
