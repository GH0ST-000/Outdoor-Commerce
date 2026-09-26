<?php

declare(strict_types=1);

namespace App\Domains\Geography\Services;

use App\Domains\Geography\Enums\SpatialSourceType;
use App\Domains\Geography\Enums\SpatialVerificationStatus;
use App\Domains\Geography\Exceptions\SpatialException;
use App\Domains\Geography\Models\SpatialSource;
use App\Domains\Geography\Support\SpatialSlug;
use App\Domains\Identity\Models\User;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Support\Facades\DB;

final class SpatialSourceWriteService
{
    public function __construct(private readonly SpatialAuditRecorder $audit) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload, User $actor): SpatialSource
    {
        $source = SpatialSource::query()->create([
            'legal_source_id' => $payload['legal_source_id'] ?? null,
            'legal_authority_id' => $payload['legal_authority_id'] ?? null,
            'name' => $payload['name'],
            'slug' => SpatialSlug::from((string) $payload['name']),
            'source_type' => SpatialSourceType::from((string) $payload['source_type']),
            'official_url' => $payload['official_url'] ?? null,
            'official_identifier' => $payload['official_identifier'] ?? null,
            'publisher_name' => $payload['publisher_name'],
            'jurisdiction_code' => $payload['jurisdiction_code'] ?? config('spatial.default_jurisdiction'),
            'license_name' => $payload['license_name'] ?? null,
            'license_url' => $payload['license_url'] ?? null,
            'attribution_text' => $payload['attribution_text'] ?? null,
            'allowed_usage_notes' => $payload['allowed_usage_notes'] ?? null,
            'verification_status' => SpatialVerificationStatus::Unverified,
            'is_active' => true,
            'is_fictional' => (bool) ($payload['is_fictional'] ?? false),
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $this->audit->record(AuditEvent::SpatialSourceCreated, $actor, 'spatial_source', $source->public_id, null, [
            'name' => $source->name,
            'jurisdiction_code' => $source->jurisdiction_code,
        ]);

        return $source;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(SpatialSource $source, array $payload, User $actor): SpatialSource
    {
        $source->fill($payload);
        $source->updated_by = $actor->id;
        $source->save();
        $this->audit->record(AuditEvent::SpatialSourceUpdated, $actor, 'spatial_source', $source->public_id);

        return $source->refresh();
    }

    public function verify(SpatialSource $source, User $actor): SpatialSource
    {
        return DB::transaction(function () use ($source, $actor): SpatialSource {
            $source->verification_status = SpatialVerificationStatus::Verified;
            $source->verified_at = now();
            $source->verified_by = $actor->id;
            $source->is_active = true;
            $source->save();
            $this->audit->record(AuditEvent::SpatialSourceVerified, $actor, 'spatial_source', $source->public_id);

            return $source->refresh();
        });
    }

    public function reject(SpatialSource $source, User $actor): SpatialSource
    {
        $source->verification_status = SpatialVerificationStatus::Rejected;
        $source->updated_by = $actor->id;
        $source->save();
        $this->audit->record(AuditEvent::SpatialSourceRejected, $actor, 'spatial_source', $source->public_id);

        return $source->refresh();
    }

    public function assertVerified(SpatialSource $source): void
    {
        if (! $source->canSupportPublication()) {
            throw SpatialException::sourceUnverified();
        }
    }
}
