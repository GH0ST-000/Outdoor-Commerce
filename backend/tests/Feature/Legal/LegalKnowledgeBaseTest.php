<?php

declare(strict_types=1);

use App\Domains\Hunting\Enums\SpeciesActivityType;
use App\Domains\Identity\Enums\Role;
use App\Domains\Identity\Models\User;
use App\Domains\Legal\Enums\LegalActivityType;
use App\Domains\Legal\Enums\LegalConclusion;
use App\Domains\Legal\Enums\LegalRuleEffect;
use App\Domains\Legal\Enums\LegalRuleStatus;
use App\Domains\Legal\Models\LegalChangeDetection;
use App\Domains\Legal\Models\LegalRule;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Operations\Models\AuditLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\InteractsWithAccessControl;
use Tests\Support\LegalFixtures;
use Tests\Support\SpeciesFixtures;

uses(InteractsWithAccessControl::class);

beforeEach(function (): void {
    Storage::fake('legal_private');
});

it('forbids guests and catalog managers from managing legal sources', function (): void {
    $this->getJson('/api/v1/admin/legal/sources')->assertUnauthorized();

    $catalog = $this->createUserWithRole(Role::CatalogManager);
    $this->actingAs($catalog, 'web')->getJson('/api/v1/admin/legal/sources')->assertForbidden();
    $this->actingAs($catalog, 'web')->postJson('/api/v1/admin/legal/rules', [])->assertForbidden();
});

it('lets a legal editor complete source, version, citation, and publication workflow', function (): void {
    $editor = $this->createUserWithRole(Role::LegalEditor);

    $authority = $this->actingAs($editor, 'web')->postJson('/api/v1/admin/legal/authorities', [
        'name' => 'FICTIONAL Ministry of Test Forests',
        'authority_type' => 'agency',
        'is_fictional' => true,
    ])->assertCreated()->json('data');

    $source = $this->actingAs($editor, 'web')->postJson('/api/v1/admin/legal/sources', [
        'authority_id' => $authority['id'],
        'name' => 'FICTIONAL official portal',
        'source_type' => 'manual_verified_source',
        'allowed_domain' => 'fictional-legal.test',
        'jurisdiction_code' => 'GE',
    ])->assertCreated()->json('data');

    $this->actingAs($editor, 'web')->postJson('/api/v1/admin/legal/sources/'.$source['id'].'/submit-review')
        ->assertOk();
    $this->actingAs($editor, 'web')->postJson('/api/v1/admin/legal/sources/'.$source['id'].'/verify')
        ->assertOk()
        ->assertJsonPath('data.verification_status', 'verified');

    $document = $this->actingAs($editor, 'web')->postJson('/api/v1/admin/legal/documents', [
        'source_id' => $source['id'],
        'title' => 'FICTIONAL Hunting Notice 2026',
        'document_type' => 'official_notice',
        'official_identifier' => 'FICT-2026-1',
    ])->assertCreated()->json('data');

    $file = UploadedFile::fake()->createWithContent('notice.txt', 'Article 1 fictional text');
    $version = $this->actingAs($editor, 'web')->post('/api/v1/admin/legal/documents/'.$document['id'].'/versions', [
        'version_label' => 'v1',
        'effective_from' => now()->subMonth()->toIso8601String(),
        'file' => $file,
    ], ['Accept' => 'application/json'])->assertCreated()->json('data');

    expect($version['checksum'])->toBe(hash('sha256', 'Article 1 fictional text'))
        ->and($version['has_file'])->toBeTrue();

    $provision = $this->actingAs($editor, 'web')->postJson('/api/v1/admin/legal/versions/'.$version['id'].'/provisions', [
        'provision_type' => 'article',
        'reference_code' => 'Art. 1',
        'official_text' => 'Hunting of the listed species is prohibited in this fictional test.',
        'normalized_summary' => 'Editorial: prohibition for tests only.',
    ])->assertCreated()->json('data');

    $this->actingAs($editor, 'web')->postJson('/api/v1/admin/legal/versions/'.$version['id'].'/submit-review')->assertOk();
    $this->actingAs($editor, 'web')->postJson('/api/v1/admin/legal/versions/'.$version['id'].'/approve')->assertOk();

    $rule = $this->actingAs($editor, 'web')->postJson('/api/v1/admin/legal/rules', [
        'title' => 'FICTIONAL hunting prohibition',
        'activity_type' => 'hunting',
        'rule_type' => 'prohibition',
        'effect' => 'prohibit',
        'jurisdiction_code' => 'GE',
        'effective_from' => now()->subMonth()->toIso8601String(),
        'interpretation_summary' => 'Fictional test prohibition. This is not legal advice.',
        'citations' => [[
            'provision_id' => $provision['id'],
            'citation_purpose' => 'authority',
            'quoted_excerpt' => 'Hunting of the listed species is prohibited',
            'is_primary' => true,
        ]],
    ])->assertCreated()->json('data');

    $this->actingAs($editor, 'web')->postJson('/api/v1/admin/legal/rules/'.$rule['id'].'/submit-review')->assertOk();
    $this->actingAs($editor, 'web')->postJson('/api/v1/admin/legal/rules/'.$rule['id'].'/approve')->assertOk();
    $this->actingAs($editor, 'web')->postJson('/api/v1/admin/legal/rules/'.$rule['id'].'/publish')
        ->assertOk()
        ->assertJsonPath('data.status', LegalRuleStatus::Published->value);

    $this->getJson('/api/v1/legal/sources')->assertOk()
        ->assertJsonPath('data.0.name', 'FICTIONAL official portal');

    $this->postJson('/api/v1/legal/evaluate', [
        'activity_type' => 'hunting',
        'jurisdiction_code' => 'GE',
        'occurred_at' => now()->toIso8601String(),
    ])->assertOk()->assertJsonPath('data.outcome', LegalConclusion::Prohibited->value);

    expect(AuditLog::query()->where('event', AuditEvent::LegalRulePublished->value)->exists())->toBeTrue();
});

it('never leaks draft rules through public legal endpoints', function (): void {
    $admin = $this->createAdmin();
    LegalRule::factory()->create([
        'title' => 'SECRET DRAFT RULE',
        'status' => LegalRuleStatus::Draft,
        'created_by' => $admin->id,
    ]);

    $this->getJson('/api/v1/legal/sources')->assertOk()
        ->assertJsonMissing(['SECRET DRAFT RULE']);
    $this->postJson('/api/v1/legal/evaluate', [
        'activity_type' => 'hunting',
        'jurisdiction_code' => 'GE',
        'occurred_at' => now()->toIso8601String(),
    ])->assertOk()->assertJsonPath('data.outcome', LegalConclusion::Unknown->value);
});

it('blocks publication of a contradictory overlapping rule', function (): void {
    $admin = $this->createAdmin();
    $stack = LegalFixtures::approvedCitationStack($admin);
    LegalFixtures::publishedRule($stack['provision'], $admin, LegalRuleEffect::Prohibit);

    $rule = $this->actingAs($admin, 'web')->postJson('/api/v1/admin/legal/rules', [
        'title' => 'FICTIONAL overlapping permission',
        'activity_type' => 'hunting',
        'rule_type' => 'permission',
        'effect' => 'allow',
        'jurisdiction_code' => 'GE',
        'effective_from' => now()->subMonth()->toIso8601String(),
        'interpretation_summary' => 'Fictional overlapping permission.',
        'citations' => [[
            'provision_id' => $stack['provision']->public_id,
            'citation_purpose' => 'authority',
            'quoted_excerpt' => 'FICTIONAL excerpt',
            'is_primary' => true,
        ]],
    ])->assertCreated()->json('data');

    $this->actingAs($admin, 'web')->postJson('/api/v1/admin/legal/rules/'.$rule['id'].'/submit-review')->assertOk();
    $this->actingAs($admin, 'web')->postJson('/api/v1/admin/legal/rules/'.$rule['id'].'/approve')->assertOk();
    $this->actingAs($admin, 'web')->postJson('/api/v1/admin/legal/rules/'.$rule['id'].'/publish')
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'LEGAL_CONFLICT_OPEN');
});

it('rejects invalid MIME types, path traversal downloads, and http source URLs', function (): void {
    $admin = $this->createAdmin();
    $authority = $this->actingAs($admin, 'web')->postJson('/api/v1/admin/legal/authorities', [
        'name' => 'FICTIONAL Agency',
        'authority_type' => 'agency',
        'is_fictional' => true,
    ])->json('data');

    $this->actingAs($admin, 'web')->postJson('/api/v1/admin/legal/sources', [
        'authority_id' => $authority['id'],
        'name' => 'Bad URL source',
        'source_type' => 'official_website',
        'official_base_url' => 'http://example.com',
    ])->assertStatus(422);

    $source = $this->actingAs($admin, 'web')->postJson('/api/v1/admin/legal/sources', [
        'authority_id' => $authority['id'],
        'name' => 'FICTIONAL upload source',
        'source_type' => 'manual_verified_source',
    ])->assertCreated()->json('data');
    $this->actingAs($admin, 'web')->postJson('/api/v1/admin/legal/sources/'.$source['id'].'/verify')->assertOk();

    $document = $this->actingAs($admin, 'web')->postJson('/api/v1/admin/legal/documents', [
        'source_id' => $source['id'],
        'title' => 'FICTIONAL doc',
        'document_type' => 'other',
    ])->assertCreated()->json('data');

    $exe = UploadedFile::fake()->create('payload.exe', 20, 'application/x-msdownload');
    $this->actingAs($admin, 'web')->post('/api/v1/admin/legal/documents/'.$document['id'].'/versions', [
        'version_label' => 'v-bad',
        'file' => $exe,
    ], ['Accept' => 'application/json'])->assertStatus(422);
});

it('forbids customers and catalog managers from downloading private legal files', function (): void {
    $admin = $this->createAdmin();
    $version = LegalFixtures::approvedCitationStack($admin)['version'];
    $download = '/api/v1/admin/legal/versions/'.$version->public_id.'/download';

    $customer = User::factory()->create();
    $this->actingAs($customer, 'web')->get($download)->assertForbidden();

    $catalog = $this->createUserWithRole(Role::CatalogManager);
    $this->app['auth']->forgetGuards();
    $this->actingAs($catalog, 'web')->get($download)->assertForbidden();
});

it('creates a source-change review item without publishing rules', function (): void {
    $admin = $this->createAdmin();
    $stack = LegalFixtures::approvedCitationStack($admin);
    $source = $stack['source'];
    $source->official_base_url = 'https://example.com/notice';
    $source->allowed_domain = 'example.com';
    $source->monitor_for_changes = true;
    $source->last_etag = '"old"';
    $source->save();
    $published = LegalFixtures::publishedRule($stack['provision'], $admin);

    Http::fake([
        'https://example.com/notice' => Http::response('', 200, ['ETag' => '"new"']),
    ]);

    $this->actingAs($admin, 'web')->postJson('/api/v1/admin/legal/sources/'.$source->public_id.'/check-for-changes')
        ->assertOk()
        ->assertJsonPath('data.changed', true);

    expect(LegalChangeDetection::query()->where('legal_source_id', $source->id)->exists())->toBeTrue();
    expect($published->fresh()->status)->toBe(LegalRuleStatus::Published);
});

it('returns unknown species legal overview until a verified rule is published', function (): void {
    $admin = $this->createAdmin();
    $species = SpeciesFixtures::published();
    $species->activity_type = SpeciesActivityType::Hunting;
    $species->save();

    $this->getJson('/api/v1/species/'.$species->canonical_slug.'/legal-overview')
        ->assertOk()
        ->assertJsonPath('data.outcome', LegalConclusion::Unknown->value)
        ->assertJsonPath('data.available', false);

    $stack = LegalFixtures::approvedCitationStack($admin);
    LegalFixtures::publishedRule($stack['provision'], $admin, LegalRuleEffect::Prohibit, LegalActivityType::Hunting, $species->id);

    $this->getJson('/api/v1/species/'.$species->canonical_slug)
        ->assertOk()
        ->assertJsonPath('data.legal_information.outcome', LegalConclusion::Prohibited->value);
});

it('rejects invalid evaluation payloads and rate-limits evaluate', function (): void {
    $this->postJson('/api/v1/legal/evaluate', [
        'activity_type' => 'not-an-activity',
        'jurisdiction_code' => 'XX',
        'occurred_at' => 'nope',
    ])->assertStatus(422);

    for ($i = 0; $i < 21; $i++) {
        $response = $this->postJson('/api/v1/legal/evaluate', [
            'activity_type' => 'hunting',
            'jurisdiction_code' => 'GE',
            'occurred_at' => now()->toIso8601String(),
        ]);
    }
    expect($response->status())->toBe(429);
});
