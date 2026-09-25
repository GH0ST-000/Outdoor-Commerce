<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Services;

use App\Domains\Hunting\Enums\SpeciesContentStatus;
use App\Domains\Hunting\Enums\SpeciesPublicationStatus;
use App\Domains\Hunting\Models\Species;
use App\Domains\Hunting\Models\SpeciesTranslation;
use App\Domains\Hunting\Support\SpeciesLocales;
use App\Domains\Hunting\Support\SpeciesLogger;

final class SpeciesTranslationResolver
{
    public function __construct(private readonly SpeciesLogger $logger) {}

    public function resolve(Species $species, string $requestedLocale, bool $publicOnly = true): ?SpeciesTranslation
    {
        $requested = SpeciesLocales::isSupported($requestedLocale)
            ? $requestedLocale
            : SpeciesLocales::default();

        $candidates = $this->orderedLocales($requested);
        foreach ($candidates as $locale) {
            $translation = $species->translation($locale);
            if ($translation === null) {
                continue;
            }

            if ($publicOnly && ! $translation->isPublished()) {
                continue;
            }

            if ($publicOnly && $locale !== $requested) {
                $this->logger->info('translation_fallback', [
                    'species_public_id' => $species->public_id,
                    'requested_locale' => $requested,
                    'resolved_locale' => $locale,
                ]);
            }

            return $translation;
        }

        return null;
    }

    public function resolvedLocale(Species $species, string $requestedLocale, bool $publicOnly = true): string
    {
        $translation = $this->resolve($species, $requestedLocale, $publicOnly);

        return $translation instanceof SpeciesTranslation
            ? $translation->locale
            : SpeciesLocales::default();
    }

    public function usedFallback(Species $species, string $requestedLocale, bool $publicOnly = true): bool
    {
        $resolved = $this->resolve($species, $requestedLocale, $publicOnly);

        return $resolved !== null && $resolved->locale !== $requestedLocale;
    }

    public function hasPublishedGeorgian(Species $species): bool
    {
        $translation = $species->translation(SpeciesLocales::default());

        return $translation !== null
            && $translation->content_status === SpeciesContentStatus::Published
            && trim($translation->common_name) !== ''
            && trim($translation->summary) !== '';
    }

    /**
     * @return list<string>
     */
    private function orderedLocales(string $requested): array
    {
        $fallbackEnabled = (bool) config('species.english_fallback.enabled', true);
        $ordered = [$requested];

        if ($fallbackEnabled && $requested !== SpeciesLocales::fallback()) {
            $ordered[] = SpeciesLocales::fallback();
        }

        return array_values(array_unique($ordered));
    }

    public function assertPubliclyVisible(Species $species): void
    {
        if ($species->publication_status !== SpeciesPublicationStatus::Published) {
            $species->unsetRelation('translations');
        }
    }
}
