<?php

declare(strict_types=1);

namespace App\Domains\Legal\DTOs;

/**
 * Safe outdoor-context snapshot. Exact coordinates are never fields on this object.
 */
final readonly class DerivedLegalContextData
{
    /**
     * @param  list<string>  $zonePublicIds
     * @param  list<string>  $zoneTypes
     * @param  list<string>  $prohibitedEquipment
     * @param  list<string>  $requiredEquipment
     * @param  list<string>  $prohibitedMethods
     * @param  list<string>  $allowedMethods
     */
    public function __construct(
        public string $conclusion,
        public bool $boundaryUncertain,
        public bool $spatiallyVerified,
        public string $activity,
        public ?int $speciesId,
        public ?string $speciesSlug,
        public ?string $speciesCategoryCode,
        public array $zonePublicIds,
        public array $zoneTypes,
        public ?string $periodFrom,
        public ?string $periodTo,
        public ?string $seasonPhase,
        public ?string $regionCode,
        public array $prohibitedEquipment,
        public array $requiredEquipment,
        public array $prohibitedMethods,
        public array $allowedMethods,
        public string $completeness,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'conclusion' => $this->conclusion,
            'boundary_uncertain' => $this->boundaryUncertain,
            'spatially_verified' => $this->spatiallyVerified,
            'activity' => $this->activity,
            'species_id' => $this->speciesId,
            'species_slug' => $this->speciesSlug,
            'species_category_code' => $this->speciesCategoryCode,
            'zone_public_ids' => array_values($this->zonePublicIds),
            'zone_types' => array_values($this->zoneTypes),
            'period_from' => $this->periodFrom,
            'period_to' => $this->periodTo,
            'season_phase' => $this->seasonPhase,
            'region_code' => $this->regionCode,
            'prohibited_equipment' => array_values($this->prohibitedEquipment),
            'required_equipment' => array_values($this->requiredEquipment),
            'prohibited_methods' => array_values($this->prohibitedMethods),
            'allowed_methods' => array_values($this->allowedMethods),
            'completeness' => $this->completeness,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            conclusion: (string) ($payload['conclusion'] ?? 'unknown'),
            boundaryUncertain: (bool) ($payload['boundary_uncertain'] ?? false),
            spatiallyVerified: (bool) ($payload['spatially_verified'] ?? false),
            activity: (string) ($payload['activity'] ?? ''),
            speciesId: isset($payload['species_id']) ? (int) $payload['species_id'] : null,
            speciesSlug: isset($payload['species_slug']) ? (string) $payload['species_slug'] : null,
            speciesCategoryCode: isset($payload['species_category_code']) ? (string) $payload['species_category_code'] : null,
            zonePublicIds: array_values(array_map('strval', $payload['zone_public_ids'] ?? [])),
            zoneTypes: array_values(array_map('strval', $payload['zone_types'] ?? [])),
            periodFrom: isset($payload['period_from']) ? (string) $payload['period_from'] : null,
            periodTo: isset($payload['period_to']) ? (string) $payload['period_to'] : null,
            seasonPhase: isset($payload['season_phase']) ? (string) $payload['season_phase'] : null,
            regionCode: isset($payload['region_code']) ? (string) $payload['region_code'] : null,
            prohibitedEquipment: array_values(array_map('strval', $payload['prohibited_equipment'] ?? [])),
            requiredEquipment: array_values(array_map('strval', $payload['required_equipment'] ?? [])),
            prohibitedMethods: array_values(array_map('strval', $payload['prohibited_methods'] ?? [])),
            allowedMethods: array_values(array_map('strval', $payload['allowed_methods'] ?? [])),
            completeness: (string) ($payload['completeness'] ?? 'insufficient'),
        );
    }
}
