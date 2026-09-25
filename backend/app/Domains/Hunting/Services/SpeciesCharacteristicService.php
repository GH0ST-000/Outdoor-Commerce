<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Services;

use App\Domains\Hunting\Enums\ActivityPattern;
use App\Domains\Hunting\Enums\MeasurementDepthUnit;
use App\Domains\Hunting\Enums\MeasurementLengthUnit;
use App\Domains\Hunting\Enums\MeasurementTemperatureUnit;
use App\Domains\Hunting\Enums\MeasurementWeightUnit;
use App\Domains\Hunting\Enums\MigrationPattern;
use App\Domains\Hunting\Enums\SocialBehavior;
use App\Domains\Hunting\Enums\WaterType;
use App\Domains\Hunting\Exceptions\SpeciesException;
use App\Domains\Hunting\Models\Species;
use App\Domains\Hunting\Models\SpeciesCharacteristic;

final class SpeciesCharacteristicService
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function sync(Species $species, array $input): SpeciesCharacteristic
    {
        $payload = $this->normalize($input);

        $existing = $species->characteristic ?? new SpeciesCharacteristic(['species_id' => $species->id]);
        $existing->fill($payload);
        $existing->species_id = $species->id;
        $existing->save();

        return $existing;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function normalize(array $input): array
    {
        $lengthMin = $this->decimal($input['average_length_min'] ?? null);
        $lengthMax = $this->decimal($input['average_length_max'] ?? null);
        $this->assertRange($lengthMin, $lengthMax, 'length');

        $weightMin = $this->decimal($input['average_weight_min'] ?? null);
        $weightMax = $this->decimal($input['average_weight_max'] ?? null);
        $this->assertRange($weightMin, $weightMax, 'weight');

        $lifeMin = $this->decimal($input['lifespan_years_min'] ?? null);
        $lifeMax = $this->decimal($input['lifespan_years_max'] ?? null);
        $this->assertRange($lifeMin, $lifeMax, 'lifespan');

        $depthMin = $this->decimal($input['preferred_depth_min'] ?? null);
        $depthMax = $this->decimal($input['preferred_depth_max'] ?? null);
        $this->assertRange($depthMin, $depthMax, 'depth');

        $tempMin = $this->decimal($input['temperature_range_min'] ?? null);
        $tempMax = $this->decimal($input['temperature_range_max'] ?? null);
        $this->assertRange($tempMin, $tempMax, 'temperature');

        return [
            'average_length_min' => $lengthMin,
            'average_length_max' => $lengthMax,
            'length_unit' => $this->enumOrNull(MeasurementLengthUnit::class, $input['length_unit'] ?? null, $lengthMin !== null || $lengthMax !== null),
            'average_weight_min' => $weightMin,
            'average_weight_max' => $weightMax,
            'weight_unit' => $this->enumOrNull(MeasurementWeightUnit::class, $input['weight_unit'] ?? null, $weightMin !== null || $weightMax !== null),
            'lifespan_years_min' => $lifeMin,
            'lifespan_years_max' => $lifeMax,
            'activity_pattern' => $this->enumOrNull(ActivityPattern::class, $input['activity_pattern'] ?? null, false),
            'social_behavior' => $this->enumOrNull(SocialBehavior::class, $input['social_behavior'] ?? null, false),
            'migration_pattern' => $this->enumOrNull(MigrationPattern::class, $input['migration_pattern'] ?? null, false),
            'water_type' => $this->enumOrNull(WaterType::class, $input['water_type'] ?? null, false),
            'preferred_depth_min' => $depthMin,
            'preferred_depth_max' => $depthMax,
            'depth_unit' => $this->enumOrNull(MeasurementDepthUnit::class, $input['depth_unit'] ?? null, $depthMin !== null || $depthMax !== null),
            'temperature_range_min' => $tempMin,
            'temperature_range_max' => $tempMax,
            'temperature_unit' => $this->enumOrNull(MeasurementTemperatureUnit::class, $input['temperature_unit'] ?? null, $tempMin !== null || $tempMax !== null),
        ];
    }

    private function decimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            throw SpeciesException::publicationInvalid(['field' => 'measurement']);
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function assertRange(?string $min, ?string $max, string $field): void
    {
        if ($min !== null && $max !== null && (float) $min > (float) $max) {
            throw SpeciesException::publicationInvalid([
                'field' => $field,
                'message' => 'Minimum cannot exceed maximum.',
            ]);
        }
    }

    /**
     * @param  class-string<\BackedEnum>  $enum
     */
    private function enumOrNull(string $enum, mixed $value, bool $required): mixed
    {
        if ($value === null || $value === '') {
            if ($required) {
                throw SpeciesException::publicationInvalid(['field' => 'unit']);
            }

            return null;
        }

        return $enum::from((string) $value);
    }
}
