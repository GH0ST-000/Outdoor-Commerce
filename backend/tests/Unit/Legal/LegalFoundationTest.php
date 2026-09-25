<?php

declare(strict_types=1);

use App\Domains\Legal\DTOs\LegalEvaluationFactsData;
use App\Domains\Legal\Enums\ConditionOperator;
use App\Domains\Legal\Enums\ConditionValueType;
use App\Domains\Legal\Enums\ExceptionRelationshipType;
use App\Domains\Legal\Enums\LegalActivityType;
use App\Domains\Legal\Enums\LegalConclusion;
use App\Domains\Legal\Enums\LegalRuleEffect;
use App\Domains\Legal\Enums\LegalRuleStatus;
use App\Domains\Legal\Enums\LegalVerificationLevel;
use App\Domains\Legal\Exceptions\LegalException;
use App\Domains\Legal\Models\LegalConflict;
use App\Domains\Legal\Models\LegalRule;
use App\Domains\Legal\Models\LegalRuleLimit;
use App\Domains\Legal\Services\LegalCitationIntegrityValidator;
use App\Domains\Legal\Services\LegalConditionValidator;
use App\Domains\Legal\Services\LegalConflictDetector;
use App\Domains\Legal\Services\LegalFileIngestionService;
use App\Domains\Legal\Services\LegalPublicationValidator;
use App\Domains\Legal\Services\LegalRuleEvaluator;
use App\Domains\Legal\Services\LegalRuleStateMachine;
use App\Domains\Legal\Services\LegalRuleWriteService;
use App\Domains\Legal\Support\LegalUrlGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\InteractsWithAccessControl;
use Tests\Support\LegalFixtures;

uses(InteractsWithAccessControl::class, RefreshDatabase::class);

it('generates sha-256 checksums for uploaded legal files', function (): void {
    Storage::fake('legal_private');
    $file = UploadedFile::fake()->createWithContent('notice.txt', 'official text');
    $stored = app(LegalFileIngestionService::class)->storeUpload($file);

    expect($stored['checksum'])->toBe(hash('sha256', 'official text'))
        ->and($stored['original'])->toBe('notice.txt')
        ->and($stored['mime'])->toBe('text/plain');
});

it('rejects double-extension filenames and empty uploads', function (): void {
    $service = app(LegalFileIngestionService::class);
    $evil = UploadedFile::fake()->create('payload.php.txt', 12, 'text/plain');
    expect(fn () => $service->assertSafeUpload($evil))->toThrow(LegalException::class);

    $empty = UploadedFile::fake()->createWithContent('empty.txt', '');
    expect(fn () => $service->assertSafeUpload($empty))->toThrow(LegalException::class);
});

it('rejects unsafe URL schemes, credentials, custom ports, and loopback hosts', function (): void {
    $guard = app(LegalUrlGuard::class);
    expect(fn () => $guard->assertRegisteredHttps('http://example.com', null))->toThrow(LegalException::class);
    expect(fn () => $guard->assertRegisteredHttps('https://user:pass@example.com', null))->toThrow(LegalException::class);
    expect(fn () => $guard->assertRegisteredHttps('https://example.com:8443/path', null))->toThrow(LegalException::class);
    expect(fn () => $guard->assertRegisteredHttps('https://localhost/x', null))->toThrow(LegalException::class);
    expect($guard->isBlockedIp('127.0.0.1'))->toBeTrue()
        ->and($guard->isBlockedIp('10.0.0.4'))->toBeTrue()
        ->and($guard->isBlockedIp('169.254.1.1'))->toBeTrue();
});

it('validates typed condition operator compatibility', function (): void {
    $validator = app(LegalConditionValidator::class);
    $validator->assertValid([
        'operator' => ConditionOperator::Equals->value,
        'value_type' => ConditionValueType::Integer->value,
        'integer_value' => 2,
    ]);
    expect(fn () => $validator->assertValid([
        'operator' => ConditionOperator::GreaterThan->value,
        'value_type' => ConditionValueType::String->value,
        'string_value' => 'x',
    ]))->toThrow(LegalException::class);
});

it('blocks invalid rule transitions', function (): void {
    $machine = app(LegalRuleStateMachine::class);
    expect($machine->canTransition(LegalRuleStatus::Draft, LegalRuleStatus::Published))->toBeFalse();
    expect(fn () => $machine->assertTransition(LegalRuleStatus::Draft, LegalRuleStatus::Published))
        ->toThrow(LegalException::class);
});

it('returns unknown when no published evidence exists', function (): void {
    $result = app(LegalRuleEvaluator::class)->evaluate(new LegalEvaluationFactsData(
        activityType: LegalActivityType::Hunting,
        jurisdictionCode: 'GE',
        occurredAt: now(),
    ));
    expect($result['outcome'])->toBe(LegalConclusion::Unknown->value)
        ->and($result['disclaimer'])->toBe('legal.informational_not_advice');
});

it('returns prohibited when a published prohibition matches', function (): void {
    $admin = $this->createAdmin();
    $stack = LegalFixtures::approvedCitationStack($admin);
    LegalFixtures::publishedRule($stack['provision'], $admin, LegalRuleEffect::Prohibit);

    $result = app(LegalRuleEvaluator::class)->evaluate(new LegalEvaluationFactsData(
        activityType: LegalActivityType::Hunting,
        jurisdictionCode: 'GE',
        occurredAt: now(),
    ));
    expect($result['outcome'])->toBe(LegalConclusion::Prohibited->value)
        ->and($result['citations'])->not->toBeEmpty();
});

it('does not infer allowed from the absence of a prohibition', function (): void {
    $result = app(LegalRuleEvaluator::class)->evaluate(new LegalEvaluationFactsData(
        activityType: LegalActivityType::Fishing,
        jurisdictionCode: 'GE',
        occurredAt: now(),
    ));
    expect($result['outcome'])->toBe(LegalConclusion::Unknown->value);
});

it('returns allowed only when a published permission matches', function (): void {
    $admin = $this->createAdmin();
    $stack = LegalFixtures::approvedCitationStack($admin);
    LegalFixtures::publishedRule($stack['provision'], $admin, LegalRuleEffect::Allow, LegalActivityType::Hunting, null, 'FICTIONAL allow');

    $result = app(LegalRuleEvaluator::class)->evaluate(new LegalEvaluationFactsData(
        activityType: LegalActivityType::Hunting,
        jurisdictionCode: 'GE',
        occurredAt: now(),
    ));
    expect($result['outcome'])->toBe(LegalConclusion::Allowed->value);
});

it('returns conflict when unresolved high-severity overlapping rules exist', function (): void {
    $admin = $this->createAdmin();
    $stack = LegalFixtures::approvedCitationStack($admin);
    $prohibit = LegalFixtures::publishedRule($stack['provision'], $admin, LegalRuleEffect::Prohibit, LegalActivityType::Hunting, null, 'FICTIONAL prohibit');
    $allow = LegalFixtures::publishedRule($stack['provision'], $admin, LegalRuleEffect::Allow, LegalActivityType::Hunting, null, 'FICTIONAL allow');
    app(LegalConflictDetector::class)->detectFor($allow);

    $result = app(LegalRuleEvaluator::class)->evaluate(new LegalEvaluationFactsData(
        activityType: LegalActivityType::Hunting,
        jurisdictionCode: 'GE',
        occurredAt: now(),
    ));
    expect($result['outcome'])->toBe(LegalConclusion::Conflict->value)
        ->and(LegalConflict::query()->count())->toBeGreaterThan(0);
    expect($prohibit->id)->not->toBe($allow->id);
});

it('requires a primary authority citation before publication', function (): void {
    $admin = $this->createAdmin();
    $rule = LegalRule::factory()->create([
        'created_by' => $admin->id,
        'status' => LegalRuleStatus::Approved,
        'reviewed_by' => $admin->id,
        'reviewed_at' => now(),
        'verification_level' => LegalVerificationLevel::ProvisionVerified,
    ]);
    expect(fn () => app(LegalCitationIntegrityValidator::class)->assertPublishable($rule))
        ->toThrow(LegalException::class);
});

it('prevents a rule from excepting itself', function (): void {
    $admin = $this->createAdmin();
    $rule = LegalRule::factory()->create(['created_by' => $admin->id]);
    $service = app(LegalRuleWriteService::class);
    expect(fn () => $service->update($rule, [
        'exceptions' => [[
            'exception_rule_id' => $rule->public_id,
            'relationship_type' => ExceptionRelationshipType::ExceptionTo->value,
        ]],
    ], $admin))->toThrow(LegalException::class);
});

it('rejects negative limits on publication', function (): void {
    $admin = $this->createAdmin();
    $rule = LegalRule::factory()->create([
        'created_by' => $admin->id,
        'status' => LegalRuleStatus::Approved,
        'reviewed_by' => $admin->id,
        'reviewed_at' => now(),
        'verification_level' => LegalVerificationLevel::ProvisionVerified,
        'interpretation_summary' => 'Summary',
    ]);
    LegalRuleLimit::query()->create([
        'legal_rule_id' => $rule->id,
        'limit_type' => 'bag',
        'amount' => -1,
        'unit' => 'animal',
        'period' => 'day',
        'applies_per' => 'person',
    ]);
    $rule->load('limits');
    expect(fn () => app(LegalPublicationValidator::class)->assertLimit($rule->limits->first()))
        ->toThrow(LegalException::class);
});
