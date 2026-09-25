<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domains\Identity\Models\User;
use App\Domains\Legal\Enums\CitationPurpose;
use App\Domains\Legal\Enums\LegalActivityType;
use App\Domains\Legal\Enums\LegalRuleEffect;
use App\Domains\Legal\Enums\LegalRuleStatus;
use App\Domains\Legal\Enums\LegalRuleType;
use App\Domains\Legal\Enums\LegalVerificationLevel;
use App\Domains\Legal\Models\LegalAuthority;
use App\Domains\Legal\Models\LegalDocument;
use App\Domains\Legal\Models\LegalDocumentVersion;
use App\Domains\Legal\Models\LegalProvision;
use App\Domains\Legal\Models\LegalRule;
use App\Domains\Legal\Models\LegalRuleCitation;
use App\Domains\Legal\Models\LegalSource;
use App\Domains\Legal\Services\LegalPublicCache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class LegalFixtures
{
    /**
     * @return array{
     *     authority: LegalAuthority,
     *     source: LegalSource,
     *     document: LegalDocument,
     *     version: LegalDocumentVersion,
     *     provision: LegalProvision
     * }
     */
    public static function approvedCitationStack(?User $reviewer = null): array
    {
        Storage::fake('legal_private');

        $authority = LegalAuthority::factory()->create();
        $source = LegalSource::factory()->verified()->create([
            'legal_authority_id' => $authority->id,
            'verified_by' => $reviewer?->id,
        ]);
        $document = LegalDocument::factory()->create([
            'legal_source_id' => $source->id,
            'legal_authority_id' => $authority->id,
        ]);
        $body = 'FICTIONAL official file '.$source->public_id;
        $path = 'test/'.$document->public_id.'.txt';
        Storage::disk('legal_private')->put($path, $body);
        $version = LegalDocumentVersion::factory()->approved()->create([
            'legal_document_id' => $document->id,
            'content_checksum' => hash('sha256', $body),
            'storage_path' => $path,
            'reviewed_by' => $reviewer?->id,
        ]);
        $document->current_version_id = $version->id;
        $document->save();
        $provision = LegalProvision::factory()->approved()->create([
            'legal_document_version_id' => $version->id,
            'reviewed_by' => $reviewer?->id,
        ]);

        return compact('authority', 'source', 'document', 'version', 'provision');
    }

    public static function publishedRule(
        LegalProvision $provision,
        User $actor,
        LegalRuleEffect $effect = LegalRuleEffect::Prohibit,
        LegalActivityType $activity = LegalActivityType::Hunting,
        ?int $speciesId = null,
        string $title = 'FICTIONAL published rule',
    ): LegalRule {
        $rule = LegalRule::factory()->create([
            'title' => $title.' '.Str::lower(Str::random(4)),
            'activity_type' => $activity,
            'rule_type' => $effect === LegalRuleEffect::Allow
                ? LegalRuleType::Permission
                : LegalRuleType::Prohibition,
            'effect' => $effect,
            'species_id' => $speciesId,
            'status' => LegalRuleStatus::Published,
            'verification_level' => LegalVerificationLevel::LegallyReviewed,
            'created_by' => $actor->id,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'published_by' => $actor->id,
            'published_at' => now(),
        ]);
        LegalRuleCitation::query()->create([
            'legal_rule_id' => $rule->id,
            'legal_provision_id' => $provision->id,
            'citation_purpose' => CitationPurpose::Authority,
            'quoted_excerpt' => 'FICTIONAL excerpt',
            'is_primary' => true,
        ]);
        app(LegalPublicCache::class)->bump();

        return $rule->refresh();
    }
}
