<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Legal\Enums\LegalSourceType;
use App\Domains\Legal\Enums\LegalVerificationStatus;
use App\Domains\Legal\Models\LegalAuthority;
use App\Domains\Legal\Models\LegalSource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LegalSource>
 */
class LegalSourceFactory extends Factory
{
    protected $model = LegalSource::class;

    public function definition(): array
    {
        $name = 'FICTIONAL Test Source '.$this->faker->unique()->numerify('###');

        return [
            'public_id' => (string) Str::uuid(),
            'legal_authority_id' => LegalAuthority::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'source_type' => LegalSourceType::ManualVerifiedSource,
            'official_base_url' => null,
            'allowed_domain' => 'fictional-legal.test',
            'language_code' => 'ka',
            'jurisdiction_code' => 'GE',
            'trust_level' => 'unverified',
            'verification_status' => LegalVerificationStatus::Unverified,
            'is_active' => true,
            'monitor_for_changes' => false,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn () => [
            'verification_status' => LegalVerificationStatus::Verified,
            'verified_at' => now(),
            'trust_level' => 'verified',
        ]);
    }
}
