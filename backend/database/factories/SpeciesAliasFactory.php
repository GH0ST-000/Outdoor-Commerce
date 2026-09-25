<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Hunting\Enums\SpeciesAliasType;
use App\Domains\Hunting\Models\Species;
use App\Domains\Hunting\Models\SpeciesAlias;
use App\Domains\Hunting\Support\AliasNormalizer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpeciesAlias>
 */
class SpeciesAliasFactory extends Factory
{
    protected $model = SpeciesAlias::class;

    public function definition(): array
    {
        $name = 'ალტერნატიული '.$this->faker->unique()->numerify('##');

        return [
            'species_id' => Species::factory(),
            'locale' => 'ka',
            'name' => $name,
            'normalized_name' => AliasNormalizer::normalize($name),
            'type' => SpeciesAliasType::CommonAlias,
            'is_searchable' => true,
            'is_public' => true,
            'source_id' => null,
        ];
    }
}
