<?php

declare(strict_types=1);

namespace App\Domains\Legal\Services;

use App\Domains\Legal\Enums\LegalConclusion;
use App\Domains\Legal\Enums\LegalReviewStatus;
use App\Domains\Legal\Enums\LegalRuleStatus;
use App\Domains\Legal\Enums\LegalVerificationStatus;
use App\Domains\Legal\Models\LegalChangeDetection;
use App\Domains\Legal\Models\LegalConflict;
use App\Domains\Legal\Models\LegalDocument;
use App\Domains\Legal\Models\LegalDocumentVersion;
use App\Domains\Legal\Models\LegalProvision;
use App\Domains\Legal\Models\LegalRule;
use App\Domains\Legal\Models\LegalRuleCitation;
use App\Domains\Legal\Models\LegalRuleCondition;
use App\Domains\Legal\Models\LegalRuleLimit;
use App\Domains\Legal\Models\LegalSource;

final class LegalPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function sourceAdmin(LegalSource $source): array
    {
        $source->loadMissing('authority');

        return [
            'id' => $source->public_id,
            'name' => $source->name,
            'slug' => $source->slug,
            'source_type' => $source->source_type->value,
            'official_base_url' => $source->official_base_url,
            'allowed_domain' => $source->allowed_domain,
            'language_code' => $source->language_code,
            'jurisdiction_code' => $source->jurisdiction_code,
            'verification_status' => $source->verification_status->value,
            'verified_at' => $source->verified_at?->toIso8601String(),
            'is_active' => $source->is_active,
            'monitor_for_changes' => $source->monitor_for_changes,
            'last_checked_at' => $source->last_checked_at?->toIso8601String(),
            'last_change_detected_at' => $source->last_change_detected_at?->toIso8601String(),
            'authority' => [
                'id' => $source->authority?->public_id,
                'name' => $source->authority?->name,
                'is_fictional' => (bool) $source->authority?->is_fictional,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sourcePublic(LegalSource $source): array
    {
        if (! $source->isPublishableSource()) {
            return [];
        }

        $source->loadMissing(['authority', 'documents.currentVersion']);

        return [
            'id' => $source->public_id,
            'name' => $source->name,
            'slug' => $source->slug,
            'source_type' => $source->source_type->value,
            'official_url' => $source->official_base_url,
            'jurisdiction_code' => $source->jurisdiction_code,
            'verified_at' => $source->verified_at?->toIso8601String(),
            'authority' => [
                'name' => $source->authority?->name,
                'type' => $source->authority?->authority_type->value,
            ],
            'documents' => $source->documents
                ->filter(fn (LegalDocument $document): bool => $document->currentVersion?->review_status === LegalReviewStatus::Approved)
                ->map(fn (LegalDocument $document): array => [
                    'title' => $document->title,
                    'official_identifier' => $document->official_identifier,
                    'official_url' => $document->official_url,
                    'effective_from' => $document->currentVersion?->effective_from?->toIso8601String(),
                    'effective_until' => $document->currentVersion?->effective_until?->toIso8601String(),
                ])->values()->all(),
            'disclaimer' => (string) config('legal.evaluation.disclaimer_key'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function documentAdmin(LegalDocument $document): array
    {
        $document->loadMissing(['source', 'authority', 'versions', 'currentVersion']);

        return [
            'id' => $document->public_id,
            'title' => $document->title,
            'slug' => $document->slug,
            'official_identifier' => $document->official_identifier,
            'document_type' => $document->document_type->value,
            'status' => $document->status->value,
            'official_url' => $document->official_url,
            'jurisdiction_code' => $document->jurisdiction_code,
            'source_id' => $document->source?->public_id,
            'current_version_id' => $document->currentVersion?->public_id,
            'versions' => $document->versions->map(fn (LegalDocumentVersion $version): array => $this->versionAdmin($version))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function versionAdmin(LegalDocumentVersion $version): array
    {
        return [
            'id' => $version->public_id,
            'version_label' => $version->version_label,
            'checksum' => $version->content_checksum,
            'checksum_algorithm' => $version->checksum_algorithm,
            'mime_type' => $version->mime_type,
            'file_size' => $version->file_size,
            'original_filename' => $version->original_filename,
            'effective_from' => $version->effective_from?->toIso8601String(),
            'effective_until' => $version->effective_until?->toIso8601String(),
            'review_status' => $version->review_status->value,
            'verification_status' => $version->verification_status->value,
            'change_summary' => $version->change_summary,
            'has_file' => $version->storage_path !== null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function provisionAdmin(LegalProvision $provision): array
    {
        return [
            'id' => $provision->public_id,
            'parent_id' => $provision->parent?->public_id,
            'provision_type' => $provision->provision_type->value,
            'reference_code' => $provision->reference_code,
            'heading' => $provision->heading,
            'official_text' => $provision->official_text,
            'normalized_summary' => $provision->normalized_summary,
            'summary_is_editorial' => true,
            'sort_order' => $provision->sort_order,
            'review_status' => $provision->review_status->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function ruleAdmin(LegalRule $rule): array
    {
        $rule->loadMissing(['citations.provision', 'conditions', 'limits', 'species']);

        return [
            'id' => $rule->public_id,
            'title' => $rule->title,
            'slug' => $rule->slug,
            'activity_type' => $rule->activity_type->value,
            'rule_type' => $rule->rule_type->value,
            'effect' => $rule->effect->value,
            'species_id' => $rule->species?->public_id,
            'jurisdiction_code' => $rule->jurisdiction_code,
            'region_code' => $rule->region_code,
            'zone_reference' => $rule->zone_reference,
            'effective_from' => $rule->effective_from->toIso8601String(),
            'effective_until' => $rule->effective_until?->toIso8601String(),
            'priority' => $rule->priority,
            'status' => $rule->status->value,
            'verification_level' => $rule->verification_level->value,
            'interpretation_summary' => $rule->interpretation_summary,
            'public_notes' => $rule->public_notes,
            'internal_notes' => $rule->internal_notes,
            'content_version' => $rule->content_version,
            'reviewed_at' => $rule->reviewed_at?->toIso8601String(),
            'published_at' => $rule->published_at?->toIso8601String(),
            'citations' => $rule->citations->map(fn (LegalRuleCitation $citation): array => [
                'provision_id' => $citation->provision?->public_id,
                'reference_code' => $citation->provision?->reference_code,
                'purpose' => $citation->citation_purpose->value,
                'excerpt' => $citation->quoted_excerpt,
                'is_primary' => $citation->is_primary,
            ])->values()->all(),
            'conditions' => $rule->conditions->map(fn (LegalRuleCondition $condition): array => [
                'condition_type' => $condition->condition_type->value,
                'operator' => $condition->operator->value,
                'value_type' => $condition->value_type->value,
                'string_value' => $condition->string_value,
                'integer_value' => $condition->integer_value,
                'decimal_value' => $condition->decimal_value,
                'boolean_value' => $condition->boolean_value,
                'date_value' => $condition->date_value?->toDateString(),
                'reference_id' => $condition->reference_id,
            ])->values()->all(),
            'limits' => $rule->limits->map(fn (LegalRuleLimit $limit): array => [
                'limit_type' => $limit->limit_type->value,
                'amount' => $limit->amount,
                'unit' => $limit->unit,
                'period' => $limit->period->value,
                'minimum_value' => $limit->minimum_value,
                'maximum_value' => $limit->maximum_value,
                'applies_per' => $limit->applies_per->value,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        return [
            'sources_awaiting_verification' => LegalSource::query()->where('verification_status', LegalVerificationStatus::PendingReview)->count(),
            'versions_awaiting_review' => LegalDocumentVersion::query()->where('review_status', LegalReviewStatus::InReview)->count(),
            'rules_awaiting_review' => LegalRule::query()->where('status', LegalRuleStatus::InReview)->count(),
            'open_high_conflicts' => LegalConflict::query()->where('severity', 'high')->whereIn('status', ['open', 'under_review'])->count(),
            'open_change_detections' => LegalChangeDetection::query()->where('status', 'open')->count(),
            'recently_published_rules' => LegalRule::query()->published()->latest('published_at')->limit(5)->get()
                ->map(fn (LegalRule $rule): array => ['id' => $rule->public_id, 'title' => $rule->title])->all(),
            'documents_without_current_version' => LegalDocument::query()->whereNull('current_version_id')->count(),
            'disclaimer' => (string) config('legal.evaluation.disclaimer_key'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function unknownOverview(): array
    {
        return [
            'available' => false,
            'outcome' => LegalConclusion::Unknown->value,
            'message_key' => 'species.legal_information_not_yet_available',
            'summary' => 'No verified published legal rules are available for this species. Absence of a prohibition is not permission. This is not legal advice.',
            'rules' => [],
            'limits' => [],
            'citations' => [],
            'conflicts' => [],
            'last_verified_at' => null,
            'disclaimer' => (string) config('legal.evaluation.disclaimer_key'),
        ];
    }
}
