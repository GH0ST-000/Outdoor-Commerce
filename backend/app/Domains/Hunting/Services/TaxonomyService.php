<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Services;

use App\Domains\Hunting\Enums\TaxonomicRank;
use App\Domains\Hunting\Exceptions\SpeciesException;
use App\Domains\Hunting\Support\ScientificNameNormalizer;

/**
 * Validated taxonomic fields — not a node tree. Rank labels are scientific
 * identifiers; localized display names stay on translations.
 */
final class TaxonomyService
{
    /**
     * @var list<string>
     */
    private const KINGDOMS = ['Animalia', 'Plantae', 'Fungi'];

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    public function normalize(array $fields): array
    {
        $scientific = ScientificNameNormalizer::display((string) ($fields['scientific_name'] ?? ''));
        if ($scientific === '' || ! ScientificNameNormalizer::isValidBinomial($scientific)) {
            throw SpeciesException::taxonomyInvalid('Scientific name must be a Latin binomial or trinomial.');
        }

        $rank = TaxonomicRank::from((string) ($fields['taxonomic_rank'] ?? TaxonomicRank::Species->value));
        $kingdom = $this->titleCase((string) ($fields['kingdom'] ?? ''));
        if (! in_array($kingdom, self::KINGDOMS, true)) {
            throw SpeciesException::taxonomyInvalid('Kingdom must be a known scientific kingdom.');
        }

        $genus = ScientificNameNormalizer::genus($scientific);
        $epithet = ScientificNameNormalizer::epithet($scientific);

        $providedGenus = isset($fields['genus']) && is_string($fields['genus']) && $fields['genus'] !== ''
            ? $this->titleCase($fields['genus'])
            : $genus;
        $providedEpithet = isset($fields['species_epithet']) && is_string($fields['species_epithet']) && $fields['species_epithet'] !== ''
            ? mb_strtolower(trim($fields['species_epithet']), 'UTF-8')
            : $epithet;

        if ($providedGenus !== $genus) {
            throw SpeciesException::taxonomyInvalid('Genus must match the scientific name.');
        }

        if ($rank === TaxonomicRank::Species || $rank === TaxonomicRank::Subspecies) {
            if ($providedEpithet !== $epithet) {
                throw SpeciesException::taxonomyInvalid('Species epithet must match the scientific name.');
            }
        }

        return [
            'scientific_name' => $scientific,
            'scientific_name_normalized' => ScientificNameNormalizer::normalize($scientific),
            'scientific_name_authorship' => $this->nullableString($fields['scientific_name_authorship'] ?? null),
            'taxonomic_rank' => $rank,
            'kingdom' => $kingdom,
            'phylum' => $this->titleCaseNullable($fields['phylum'] ?? null),
            'class_name' => $this->titleCaseNullable($fields['class_name'] ?? null),
            'order_name' => $this->titleCaseNullable($fields['order_name'] ?? null),
            'family' => $this->titleCaseNullable($fields['family'] ?? null),
            'genus' => $providedGenus,
            'species_epithet' => $providedEpithet,
        ];
    }

    /**
     * @return list<string>
     */
    public function allowedKingdoms(): array
    {
        return self::KINGDOMS;
    }

    private function titleCase(string $value): string
    {
        return mb_convert_case(trim($value), MB_CASE_TITLE, 'UTF-8');
    }

    private function titleCaseNullable(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return $this->titleCase($value);
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
