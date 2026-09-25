<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Services;

use App\Domains\Hunting\Enums\SpeciesAliasType;
use App\Domains\Hunting\Exceptions\SpeciesException;
use App\Domains\Hunting\Models\KnowledgeSource;
use App\Domains\Hunting\Models\Species;
use App\Domains\Hunting\Models\SpeciesAlias;
use App\Domains\Hunting\Support\AliasNormalizer;
use App\Domains\Hunting\Support\SpeciesLocales;

final class SpeciesAliasService
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function create(Species $species, array $input): SpeciesAlias
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw SpeciesException::aliasDuplicate();
        }

        $normalized = AliasNormalizer::normalize($name);
        $locale = isset($input['locale']) && is_string($input['locale']) && $input['locale'] !== ''
            ? $input['locale']
            : null;
        if ($locale !== null && ! SpeciesLocales::isSupported($locale)) {
            $locale = null;
        }

        $exists = SpeciesAlias::query()
            ->where('species_id', $species->id)
            ->where('normalized_name', $normalized)
            ->where(function ($query) use ($locale): void {
                if ($locale === null) {
                    $query->whereNull('locale');
                } else {
                    $query->where('locale', $locale);
                }
            })
            ->exists();

        if ($exists) {
            throw SpeciesException::aliasDuplicate();
        }

        $type = SpeciesAliasType::from((string) ($input['type'] ?? SpeciesAliasType::CommonAlias->value));
        $sourceId = KnowledgeSource::resolveKey($input['source_id'] ?? $input['source_public_id'] ?? null);

        if ($type === SpeciesAliasType::ScientificSynonym && $sourceId === null) {
            throw SpeciesException::sourceRequired();
        }

        if (($input['source_id'] ?? $input['source_public_id'] ?? null) && $sourceId === null) {
            throw SpeciesException::notFound();
        }

        return SpeciesAlias::query()->create([
            'species_id' => $species->id,
            'locale' => $locale,
            'name' => $name,
            'normalized_name' => $normalized,
            'type' => $type,
            'is_searchable' => (bool) ($input['is_searchable'] ?? true),
            'is_public' => (bool) ($input['is_public'] ?? false),
            'source_id' => $sourceId,
        ]);
    }
}
