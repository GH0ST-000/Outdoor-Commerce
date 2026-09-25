<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Legal\Enums\LegalDocumentStatus;
use App\Domains\Legal\Enums\LegalDocumentType;
use App\Domains\Legal\Models\LegalAuthority;
use App\Domains\Legal\Models\LegalDocument;
use App\Domains\Legal\Models\LegalSource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LegalDocument>
 */
class LegalDocumentFactory extends Factory
{
    protected $model = LegalDocument::class;

    public function definition(): array
    {
        $title = 'FICTIONAL Test Regulation '.$this->faker->unique()->numerify('###');
        $source = LegalSource::factory()->verified();

        return [
            'public_id' => (string) Str::uuid(),
            'legal_source_id' => $source,
            'legal_authority_id' => LegalAuthority::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'official_identifier' => 'FICT-'.$this->faker->unique()->numerify('####'),
            'document_type' => LegalDocumentType::Regulation,
            'jurisdiction_code' => 'GE',
            'language_code' => 'ka',
            'official_url' => null,
            'status' => LegalDocumentStatus::Draft,
        ];
    }
}
