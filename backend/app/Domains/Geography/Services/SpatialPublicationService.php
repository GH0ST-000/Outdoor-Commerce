<?php

declare(strict_types=1);

namespace App\Domains\Geography\Services;

use App\Domains\Geography\Enums\SpatialAssignmentStatus;
use App\Domains\Geography\Enums\SpatialAssignmentType;
use App\Domains\Geography\Enums\SpatialDatasetStatus;
use App\Domains\Geography\Enums\SpatialGeometryVersionStatus;
use App\Domains\Geography\Enums\SpatialImportStatus;
use App\Domains\Geography\Enums\SpatialReviewStatus;
use App\Domains\Geography\Enums\SpatialValidationStatus;
use App\Domains\Geography\Enums\SpatialZoneStatus;
use App\Domains\Geography\Exceptions\SpatialException;
use App\Domains\Geography\Models\LegalRuleSpatialZone;
use App\Domains\Geography\Models\SpatialDataset;
use App\Domains\Geography\Models\SpatialDatasetVersion;
use App\Domains\Geography\Models\SpatialZone;
use App\Domains\Geography\Models\SpatialZoneGeometryVersion;
use App\Domains\Identity\Models\User;
use App\Domains\Legal\Enums\LegalRuleStatus;
use App\Domains\Legal\Models\LegalRule;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class SpatialPublicationService
{
    /**
     * @var array<string, list<string>>
     */
    private const VERSION_TRANSITIONS = [
        'draft' => ['validating', 'in_review'],
        'validating' => ['draft', 'in_review'],
        'in_review' => ['draft', 'approved', 'rejected'],
        'approved' => ['published', 'in_review', 'rejected'],
        'published' => ['superseded', 'archived'],
        'rejected' => ['draft'],
        'superseded' => ['archived'],
        'archived' => [],
    ];

    public function __construct(
        private readonly SpatialAuditRecorder $audit,
        private readonly SpatialPublicCache $cache,
        private readonly SpatialDatasetWriteService $datasets,
    ) {}

    public function submitReview(SpatialDatasetVersion $version, User $actor): SpatialDatasetVersion
    {
        $this->assertTransition($version->review_status, SpatialReviewStatus::InReview);
        if (! in_array($version->import_status, [SpatialImportStatus::Imported, SpatialImportStatus::PartiallyFailed], true)) {
            throw SpatialException::publicationInvalid(['import_status' => $version->import_status->value]);
        }
        $version->review_status = SpatialReviewStatus::InReview;
        $version->save();
        $this->audit->record(AuditEvent::SpatialDatasetVersionSubmittedReview, $actor, 'spatial_dataset_version', $version->public_id);

        return $version->refresh();
    }

    public function approve(SpatialDatasetVersion $version, User $actor): SpatialDatasetVersion
    {
        $this->assertTransition($version->review_status, SpatialReviewStatus::Approved);
        $version->review_status = SpatialReviewStatus::Approved;
        $version->reviewed_at = now();
        $version->reviewed_by = $actor->id;
        $version->save();
        $this->audit->record(AuditEvent::SpatialDatasetVersionApproved, $actor, 'spatial_dataset_version', $version->public_id);

        return $version->refresh();
    }

    public function reject(SpatialDatasetVersion $version, User $actor, string $reason): SpatialDatasetVersion
    {
        $this->assertTransition($version->review_status, SpatialReviewStatus::Rejected);
        $version->review_status = SpatialReviewStatus::Rejected;
        $version->change_summary = $reason;
        $version->reviewed_at = now();
        $version->reviewed_by = $actor->id;
        $version->save();
        $this->audit->record(AuditEvent::SpatialDatasetVersionRejected, $actor, 'spatial_dataset_version', $version->public_id, null, ['reason' => $reason]);

        return $version->refresh();
    }

    public function publish(SpatialDatasetVersion $version, User $actor): SpatialDatasetVersion
    {
        $this->assertTransition($version->review_status, SpatialReviewStatus::Published);
        $version->loadMissing('dataset.source');
        $dataset = $version->dataset ?? throw SpatialException::notFound('Spatial dataset');
        $this->datasets->assertSourceReady($dataset);
        if ((bool) config('spatial.publishing.require_distinct_publisher') && $version->reviewed_by === $actor->id) {
            throw SpatialException::publicationInvalid(['reason' => 'A distinct publisher is required.']);
        }

        $lock = Cache::lock('spatial:publish:'.$version->spatial_dataset_id, (int) config('spatial.import.lock_seconds', 120));
        if (! $lock->get()) {
            throw SpatialException::concurrentPublication();
        }

        try {
            return DB::transaction(function () use ($version, $actor): SpatialDatasetVersion {
                /** @var SpatialDataset $dataset */
                $dataset = SpatialDataset::query()->lockForUpdate()->findOrFail($version->spatial_dataset_id);
                $geometries = SpatialZoneGeometryVersion::query()
                    ->where('spatial_dataset_version_id', $version->id)
                    ->get();
                if ($geometries->isEmpty()) {
                    throw SpatialException::publicationInvalid(['reason' => 'No imported geometry to publish.']);
                }

                foreach ($geometries as $geometry) {
                    if ($geometry->validation_status === SpatialValidationStatus::Invalid) {
                        throw SpatialException::publicationInvalid(['geometry' => $geometry->public_id]);
                    }
                    SpatialZoneGeometryVersion::query()
                        ->where('spatial_zone_id', $geometry->spatial_zone_id)
                        ->where('status', SpatialGeometryVersionStatus::Published)
                        ->where('id', '!=', $geometry->id)
                        ->update([
                            'status' => SpatialGeometryVersionStatus::Superseded,
                            'effective_until' => $geometry->effective_from,
                        ]);
                    $geometry->status = SpatialGeometryVersionStatus::Published;
                    $geometry->published_at = now();
                    $geometry->published_by = $actor->id;
                    $geometry->save();
                    SpatialZone::query()->whereKey($geometry->spatial_zone_id)->update([
                        'status' => SpatialZoneStatus::Active,
                    ]);
                }

                if ($dataset->current_version_id !== null && $dataset->current_version_id !== $version->id) {
                    SpatialDatasetVersion::query()->whereKey($dataset->current_version_id)->update([
                        'review_status' => SpatialReviewStatus::Superseded,
                    ]);
                }

                $version->review_status = SpatialReviewStatus::Published;
                $version->published_at = now();
                $version->published_by = $actor->id;
                $version->save();
                $dataset->current_version_id = $version->id;
                $dataset->status = SpatialDatasetStatus::Active;
                $dataset->save();

                $this->cache->bump();
                $this->audit->record(AuditEvent::SpatialDatasetVersionPublished, $actor, 'spatial_dataset_version', $version->public_id, null, [
                    'checksum' => $version->content_checksum,
                    'feature_count' => $geometries->count(),
                ]);

                return $version->refresh();
            });
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function assignRule(SpatialZone $zone, array $payload, User $actor): LegalRuleSpatialZone
    {
        $rule = LegalRule::query()->where('public_id', $payload['legal_rule_id'])->first()
            ?? throw SpatialException::notFound('Legal rule');
        if ($rule->status !== LegalRuleStatus::Published) {
            throw SpatialException::publicationInvalid(['reason' => 'Only published legal rules may be assigned.']);
        }
        $type = SpatialAssignmentType::from((string) $payload['assignment_type']);
        if ($type === SpatialAssignmentType::AppliesOutside && trim((string) ($payload['review_notes'] ?? '')) === '') {
            throw SpatialException::publicationInvalid(['reason' => 'applies_outside requires documented review notes.']);
        }

        $geometryId = null;
        if (! empty($payload['zone_geometry_version_id'])) {
            $geometry = SpatialZoneGeometryVersion::query()
                ->where('public_id', $payload['zone_geometry_version_id'])
                ->where('spatial_zone_id', $zone->id)
                ->first() ?? throw SpatialException::notFound('Geometry version');
            if (! $geometry->isPublished()) {
                throw SpatialException::publicationInvalid(['reason' => 'Assignments must reference published geometry or omit the version.']);
            }
            $geometryId = $geometry->id;
        }

        $assignment = LegalRuleSpatialZone::query()->create([
            'legal_rule_id' => $rule->id,
            'spatial_zone_id' => $zone->id,
            'zone_geometry_version_id' => $geometryId,
            'assignment_type' => $type,
            'precedence' => (int) ($payload['precedence'] ?? 0),
            'effective_from' => $payload['effective_from'] ?? now(),
            'effective_until' => $payload['effective_until'] ?? null,
            'status' => SpatialAssignmentStatus::Published,
            'review_notes' => $payload['review_notes'] ?? null,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'created_by' => $actor->id,
        ]);

        $this->cache->bump();
        $this->audit->record(AuditEvent::SpatialRuleAssigned, $actor, 'legal_rule_spatial_zone', $assignment->public_id, null, [
            'zone_id' => $zone->public_id,
            'rule_id' => $rule->public_id,
            'assignment_type' => $type->value,
        ]);

        return $assignment;
    }

    private function assertTransition(SpatialReviewStatus $from, SpatialReviewStatus $to): void
    {
        if (! in_array($to->value, self::VERSION_TRANSITIONS[$from->value] ?? [], true)) {
            throw SpatialException::invalidTransition($from->value, $to->value);
        }
    }
}
