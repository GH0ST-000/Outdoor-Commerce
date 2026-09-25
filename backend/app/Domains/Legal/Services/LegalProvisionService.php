<?php

declare(strict_types=1);

namespace App\Domains\Legal\Services;

use App\Domains\Identity\Models\User;
use App\Domains\Legal\Enums\LegalReviewStatus;
use App\Domains\Legal\Exceptions\LegalException;
use App\Domains\Legal\Models\LegalDocumentVersion;
use App\Domains\Legal\Models\LegalProvision;
use App\Domains\Operations\Enums\AuditEvent;

final class LegalProvisionService
{
    public function __construct(
        private readonly LegalPublicCache $cache,
        private readonly LegalAuditRecorder $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(LegalDocumentVersion $version, array $input, User $actor): LegalProvision
    {
        if ($version->isLocked() && $version->review_status === LegalReviewStatus::Archived) {
            throw LegalException::versionImmutable();
        }

        $parentId = null;
        if (! empty($input['parent_provision_id'])) {
            $parent = LegalProvision::query()
                ->where('public_id', $input['parent_provision_id'])
                ->where('legal_document_version_id', $version->id)
                ->first() ?? throw LegalException::notFound('Parent provision');
            $parentId = $parent->id;
        }

        $provision = LegalProvision::query()->create([
            'legal_document_version_id' => $version->id,
            'parent_provision_id' => $parentId,
            'provision_type' => $input['provision_type'],
            'reference_code' => $input['reference_code'],
            'heading' => $input['heading'] ?? null,
            'official_text' => $input['official_text'],
            'normalized_summary' => $input['normalized_summary'] ?? null,
            'sort_order' => (int) ($input['sort_order'] ?? 0),
            'effective_from' => $input['effective_from'] ?? null,
            'effective_until' => $input['effective_until'] ?? null,
            'review_status' => LegalReviewStatus::Draft,
        ]);
        $this->audit->record(AuditEvent::LegalProvisionCreated, $actor, 'legal_provision', $provision->public_id);
        $this->cache->bump();

        return $provision;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(LegalProvision $provision, array $input, User $actor): LegalProvision
    {
        $provision->fill(array_intersect_key($input, array_flip([
            'provision_type', 'reference_code', 'heading', 'official_text', 'normalized_summary',
            'sort_order', 'effective_from', 'effective_until',
        ])));
        if (($input['review_status'] ?? null) === LegalReviewStatus::Approved->value) {
            $provision->review_status = LegalReviewStatus::Approved;
            $provision->reviewed_at = now();
            $provision->reviewed_by = $actor->id;
        }
        $provision->save();
        $this->audit->record(AuditEvent::LegalProvisionUpdated, $actor, 'legal_provision', $provision->public_id);
        $this->cache->bump();

        return $provision->refresh();
    }

    public function delete(LegalProvision $provision, User $actor): void
    {
        if ($provision->citations()->exists()) {
            throw LegalException::publicationInvalid(['reason' => 'provision_in_use']);
        }
        $id = $provision->public_id;
        $provision->delete();
        $this->audit->record(AuditEvent::LegalProvisionDeleted, $actor, 'legal_provision', $id);
        $this->cache->bump();
    }
}
