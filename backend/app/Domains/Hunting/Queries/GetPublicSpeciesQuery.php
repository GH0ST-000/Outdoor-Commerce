<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Queries;

use App\Domains\Hunting\Exceptions\SpeciesException;
use App\Domains\Hunting\Models\Species;
use App\Domains\Hunting\Services\SpeciesPresenter;
use App\Domains\Hunting\Services\SpeciesTranslationResolver;
use App\Domains\Legal\Queries\GetSpeciesLegalOverviewQuery;

final class GetPublicSpeciesQuery
{
    public function __construct(
        private readonly SpeciesPresenter $presenter,
        private readonly SpeciesTranslationResolver $resolver,
        private readonly GetSpeciesLegalOverviewQuery $legalOverview,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function bySlug(string $slug, string $locale): array
    {
        $species = Species::query()
            ->published()
            ->where('canonical_slug', $slug)
            ->with([
                'translations',
                'aliases',
                'characteristic',
                'habitatLinks.habitat.translations',
                'identificationTraits',
                'similarFrom.similarSpecies.translations',
                'similarTo.species.translations',
                'conservationAssessments.source',
                'citations.source',
                'mediaAttachments.asset.derivatives',
                'mediaAttachments.translations',
            ])
            ->first();

        if ($species === null || $this->resolver->resolve($species, $locale, true) === null) {
            throw SpeciesException::notFound();
        }

        $detail = $this->presenter->detail($species, $locale);
        $detail['legal_information'] = $this->legalOverview->forSpecies(
            $species,
            (string) config('legal.default_jurisdiction', 'GE'),
        );

        return $detail;
    }
}
