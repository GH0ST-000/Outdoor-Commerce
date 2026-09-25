<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Legal\Enums\ExtractionStatus;
use App\Domains\Legal\Enums\LegalReviewStatus;
use App\Domains\Legal\Enums\LegalVerificationStatus;
use App\Domains\Legal\Models\LegalDocument;
use App\Domains\Legal\Models\LegalDocumentVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LegalDocumentVersion>
 */
class LegalDocumentVersionFactory extends Factory
{
    protected $model = LegalDocumentVersion::class;

    public function definition(): array
    {
        $body = 'FICTIONAL test legal text '.$this->faker->unique()->uuid();

        return [
            'public_id' => (string) Str::uuid(),
            'legal_document_id' => LegalDocument::factory(),
            'version_label' => 'v1-fictional',
            'source_url' => null,
            'effective_from' => now()->subYear(),
            'effective_until' => null,
            'retrieved_at' => now(),
            'content_checksum' => hash('sha256', $body),
            'checksum_algorithm' => 'sha256',
            'mime_type' => 'text/plain',
            'file_size' => strlen($body),
            'storage_disk' => 'legal_private',
            'storage_path' => 'test/'.Str::uuid().'.txt',
            'original_filename' => 'fictional-notice.txt',
            'extraction_status' => ExtractionStatus::ManualOnly,
            'verification_status' => LegalVerificationStatus::Unverified,
            'review_status' => LegalReviewStatus::Draft,
            'change_summary' => 'Fictional test version',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'review_status' => LegalReviewStatus::Approved,
            'verification_status' => LegalVerificationStatus::Verified,
            'reviewed_at' => now(),
        ]);
    }
}
