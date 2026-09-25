<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Legal\Enums\AuthorityType;
use App\Domains\Legal\Models\LegalAuthority;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LegalAuthority>
 */
class LegalAuthorityFactory extends Factory
{
    protected $model = LegalAuthority::class;

    public function definition(): array
    {
        $name = 'FICTIONAL Test Authority '.$this->faker->unique()->numerify('###');

        return [
            'public_id' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'authority_type' => AuthorityType::Agency,
            'country_code' => 'GE',
            'jurisdiction_code' => 'GE',
            'official_website_url' => null,
            'description' => 'Fictional test authority. Not a real legal source.',
            'is_active' => true,
            'is_fictional' => true,
        ];
    }
}
