<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Services;

use App\Domains\Hunting\Enums\KnowledgeSourceType;
use App\Domains\Hunting\Enums\SpeciesVerificationStatus;
use App\Domains\Hunting\Exceptions\SpeciesException;
use App\Domains\Hunting\Models\KnowledgeCitation;
use App\Domains\Hunting\Models\KnowledgeSource;
use App\Domains\Hunting\Models\Species;
use App\Domains\Hunting\Support\SpeciesUrlValidator;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Str;

final class KnowledgeSourceService
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input, User $actor): KnowledgeSource
    {
        $url = isset($input['url']) ? trim((string) $input['url']) : null;
        $url = $url === '' ? null : $url;
        if ($url !== null && ! SpeciesUrlValidator::isSafe($url)) {
            throw SpeciesException::sourceUrlInvalid();
        }

        if ($url !== null && empty($input['retrieved_at'])) {
            throw SpeciesException::publicationInvalid(['retrieved_at' => 'Retrieved date is required for web sources.']);
        }

        $excerpt = isset($input['quotation_excerpt']) ? trim((string) $input['quotation_excerpt']) : '';
        if (mb_strlen($excerpt) > 280) {
            throw SpeciesException::contentUnsafe();
        }

        return KnowledgeSource::query()->create([
            'public_id' => (string) Str::uuid(),
            'title' => mb_substr(trim((string) $input['title']), 0, 255),
            'publisher' => mb_substr(trim((string) $input['publisher']), 0, 255),
            'source_type' => KnowledgeSourceType::from((string) $input['source_type']),
            'url' => $url,
            'document_identifier' => $input['document_identifier'] ?? null,
            'language' => $input['language'] ?? null,
            'published_at' => $input['published_at'] ?? null,
            'effective_from' => $input['effective_from'] ?? null,
            'effective_to' => $input['effective_to'] ?? null,
            'retrieved_at' => $input['retrieved_at'] ?? null,
            'is_official' => (bool) ($input['is_official'] ?? false),
            'verification_status' => SpeciesVerificationStatus::from(
                (string) ($input['verification_status'] ?? SpeciesVerificationStatus::Unverified->value),
            ),
            'notes' => $input['notes'] ?? null,
            'source_version' => 1,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function attachToSpecies(Species $species, KnowledgeSource $source, array $input = []): KnowledgeCitation
    {
        return KnowledgeCitation::query()->create([
            'source_id' => $source->id,
            'citable_type' => 'species',
            'citable_id' => $species->id,
            'claim_key' => $input['claim_key'] ?? 'species',
            'page_reference' => $input['page_reference'] ?? null,
            'section_reference' => $input['section_reference'] ?? null,
            'quotation_excerpt' => isset($input['quotation_excerpt'])
                ? mb_substr((string) $input['quotation_excerpt'], 0, 280)
                : null,
            'editor_note' => $input['editor_note'] ?? null,
        ]);
    }
}
