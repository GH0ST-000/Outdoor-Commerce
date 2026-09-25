<?php

declare(strict_types=1);

namespace App\Domains\Legal\Services;

use App\Domains\Identity\Models\User;
use App\Domains\Legal\Enums\LegalDocumentStatus;
use App\Domains\Legal\Exceptions\LegalException;
use App\Domains\Legal\Models\LegalAuthority;
use App\Domains\Legal\Models\LegalDocument;
use App\Domains\Legal\Models\LegalSource;
use App\Domains\Legal\Support\LegalSlug;
use App\Domains\Legal\Support\LegalUrlGuard;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Support\Str;

final class LegalDocumentService
{
    public function __construct(
        private readonly LegalUrlGuard $urls,
        private readonly LegalPublicCache $cache,
        private readonly LegalAuditRecorder $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input, User $actor): LegalDocument
    {
        $source = LegalSource::query()->where('public_id', $input['source_id'])->first()
            ?? throw LegalException::notFound('Legal source');
        $authority = isset($input['authority_id'])
            ? LegalAuthority::query()->where('public_id', $input['authority_id'])->first()
            : $source->authority;
        if ($authority === null) {
            throw LegalException::notFound('Legal authority');
        }
        if (isset($input['official_url']) && is_string($input['official_url']) && $input['official_url'] !== '') {
            $this->urls->assertRegisteredHttps($input['official_url'], $source->allowed_domain);
        }

        $document = LegalDocument::query()->create([
            'legal_source_id' => $source->id,
            'legal_authority_id' => $authority->id,
            'title' => $input['title'],
            'slug' => $input['slug'] ?? LegalSlug::from((string) $input['title']).'-'.substr((string) Str::uuid(), 0, 8),
            'official_identifier' => $input['official_identifier'] ?? null,
            'document_type' => $input['document_type'],
            'jurisdiction_code' => $input['jurisdiction_code'] ?? $source->jurisdiction_code,
            'language_code' => $input['language_code'] ?? $source->language_code,
            'official_url' => $input['official_url'] ?? null,
            'publication_date' => $input['publication_date'] ?? null,
            'original_effective_date' => $input['original_effective_date'] ?? null,
            'status' => LegalDocumentStatus::Draft,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
        $this->audit->record(AuditEvent::LegalDocumentCreated, $actor, 'legal_document', $document->public_id);
        $this->cache->bump();

        return $document;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(LegalDocument $document, array $input, User $actor): LegalDocument
    {
        $document->fill(array_intersect_key($input, array_flip([
            'title', 'official_identifier', 'document_type', 'jurisdiction_code', 'language_code',
            'official_url', 'publication_date', 'original_effective_date', 'status',
        ])));
        $document->updated_by = $actor->id;
        $document->save();
        $this->audit->record(AuditEvent::LegalDocumentUpdated, $actor, 'legal_document', $document->public_id);
        $this->cache->bump();

        return $document->refresh();
    }
}
