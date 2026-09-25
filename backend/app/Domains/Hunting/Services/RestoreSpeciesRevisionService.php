<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Services;

use App\Domains\Hunting\Exceptions\SpeciesException;
use App\Domains\Hunting\Models\Species;
use App\Domains\Hunting\Models\SpeciesRevision;
use App\Domains\Identity\Models\User;

final class RestoreSpeciesRevisionService
{
    public function __construct(private readonly SpeciesWriteService $write) {}

    public function restore(Species $species, int $revisionNumber, User $actor): Species
    {
        $revision = SpeciesRevision::query()
            ->where('species_id', $species->id)
            ->where('revision_number', $revisionNumber)
            ->first();

        if ($revision === null) {
            throw SpeciesException::revisionNotFound();
        }

        /** @var array<string, mixed> $snapshot */
        $snapshot = $revision->snapshot;

        $payload = [
            'content_version' => $species->content_version,
            'scientific_name' => $snapshot['scientific_name'] ?? $species->scientific_name,
            'taxonomic_rank' => $snapshot['taxonomic_rank'] ?? $species->taxonomic_rank->value,
            'kingdom' => $snapshot['kingdom'] ?? $species->kingdom,
            'phylum' => $snapshot['phylum'] ?? $species->phylum,
            'class_name' => $snapshot['class_name'] ?? $species->class_name,
            'order_name' => $snapshot['order_name'] ?? $species->order_name,
            'family' => $snapshot['family'] ?? $species->family,
            'genus' => $snapshot['genus'] ?? $species->genus,
            'species_epithet' => $snapshot['species_epithet'] ?? $species->species_epithet,
            'domain_type' => $snapshot['domain_type'] ?? $species->domain_type->value,
            'activity_type' => $snapshot['activity_type'] ?? $species->activity_type->value,
            'translations' => $snapshot['translations'] ?? [],
            'habitats' => array_values(array_filter(array_map(
                static function (array $row): ?array {
                    $code = HabitatCodeLookup::code((int) ($row['habitat_id'] ?? 0));
                    if ($code === null) {
                        return null;
                    }

                    return [
                        'code' => $code,
                        'importance' => $row['importance'] ?? 'secondary',
                    ];
                },
                $snapshot['habitats'] ?? [],
            ))),
            'identification_traits' => $snapshot['traits'] ?? [],
            'conservation' => $snapshot['conservation'] ?? [],
            'citation_source_ids' => $snapshot['citation_source_ids'] ?? [],
            'change_summary' => 'Restored revision '.$revisionNumber,
        ];

        return $this->write->update($species, $payload, $actor);
    }
}
