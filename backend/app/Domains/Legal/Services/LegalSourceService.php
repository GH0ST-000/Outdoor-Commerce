<?php

declare(strict_types=1);

namespace App\Domains\Legal\Services;

use App\Domains\Identity\Models\User;
use App\Domains\Legal\Enums\LegalVerificationStatus;
use App\Domains\Legal\Exceptions\LegalException;
use App\Domains\Legal\Models\LegalAuthority;
use App\Domains\Legal\Models\LegalSource;
use App\Domains\Legal\Support\LegalSlug;
use App\Domains\Legal\Support\LegalUrlGuard;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class LegalSourceService
{
    public function __construct(
        private readonly LegalUrlGuard $urls,
        private readonly LegalPublicCache $cache,
        private readonly LegalAuditRecorder $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input, User $actor): LegalSource
    {
        return DB::transaction(function () use ($input, $actor): LegalSource {
            $authority = LegalAuthority::query()->where('public_id', $input['authority_id'])->first()
                ?? throw LegalException::notFound('Legal authority');

            $url = isset($input['official_base_url']) ? (string) $input['official_base_url'] : null;
            $domain = isset($input['allowed_domain']) ? (string) $input['allowed_domain'] : null;
            if ($url) {
                $this->urls->assertRegisteredHttps($url, $domain);
            }

            $source = LegalSource::query()->create([
                'legal_authority_id' => $authority->id,
                'name' => $input['name'],
                'slug' => $input['slug'] ?? LegalSlug::from((string) $input['name']).'-'.substr((string) Str::uuid(), 0, 8),
                'source_type' => $input['source_type'],
                'official_base_url' => $url,
                'allowed_domain' => $domain,
                'language_code' => $input['language_code'] ?? 'ka',
                'jurisdiction_code' => $input['jurisdiction_code'] ?? config('legal.default_jurisdiction'),
                'trust_level' => $input['trust_level'] ?? 'unverified',
                'verification_status' => LegalVerificationStatus::Unverified,
                'notes' => $input['notes'] ?? null,
                'is_active' => (bool) ($input['is_active'] ?? true),
                'monitor_for_changes' => (bool) ($input['monitor_for_changes'] ?? false),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->audit->record(AuditEvent::LegalSourceCreated, $actor, 'legal_source', $source->public_id, null, ['name' => $source->name]);
            $this->cache->bump();

            return $source;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(LegalSource $source, array $input, User $actor): LegalSource
    {
        if (isset($input['official_base_url'])) {
            $this->urls->assertRegisteredHttps((string) $input['official_base_url'], $input['allowed_domain'] ?? $source->allowed_domain);
        }

        $source->fill(array_intersect_key($input, array_flip([
            'name', 'official_base_url', 'allowed_domain', 'language_code', 'jurisdiction_code',
            'trust_level', 'notes', 'is_active', 'monitor_for_changes',
        ])));
        $source->updated_by = $actor->id;
        $source->save();
        $this->audit->record(AuditEvent::LegalSourceUpdated, $actor, 'legal_source', $source->public_id, null, ['name' => $source->name]);
        $this->cache->bump();

        return $source->refresh();
    }

    public function submitReview(LegalSource $source, User $actor): LegalSource
    {
        $source->verification_status = LegalVerificationStatus::PendingReview;
        $source->updated_by = $actor->id;
        $source->save();
        $this->audit->record(AuditEvent::LegalSourceSubmittedReview, $actor, 'legal_source', $source->public_id);

        return $source;
    }

    public function verify(LegalSource $source, User $actor): LegalSource
    {
        $source->verification_status = LegalVerificationStatus::Verified;
        $source->verified_at = now();
        $source->verified_by = $actor->id;
        $source->updated_by = $actor->id;
        $source->save();
        $this->audit->record(AuditEvent::LegalSourceVerified, $actor, 'legal_source', $source->public_id);
        $this->cache->bump();

        return $source;
    }

    public function reject(LegalSource $source, User $actor, string $reason): LegalSource
    {
        $source->verification_status = LegalVerificationStatus::Rejected;
        $source->updated_by = $actor->id;
        $source->save();
        $this->audit->record(AuditEvent::LegalSourceRejected, $actor, 'legal_source', $source->public_id, null, ['reason' => $reason]);
        $this->cache->bump();

        return $source;
    }
}
