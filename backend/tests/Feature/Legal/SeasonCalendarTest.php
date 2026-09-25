<?php

declare(strict_types=1);

use App\Domains\Hunting\Models\Species;
use App\Domains\Identity\Enums\Role;
use App\Domains\Legal\DTOs\PeriodAvailabilityQueryData;
use App\Domains\Legal\Enums\AvailabilityMode;
use App\Domains\Legal\Enums\CalendarAvailabilityState;
use App\Domains\Legal\Enums\LegalActivityType;
use App\Domains\Legal\Enums\LegalRuleEffect;
use App\Domains\Legal\Enums\LegalRuleStatus;
use App\Domains\Legal\Enums\SeasonOverrideType;
use App\Domains\Legal\Enums\SeasonScheduleType;
use App\Domains\Legal\Enums\SeasonType;
use App\Domains\Legal\Models\LegalSeasonDefinition;
use App\Domains\Legal\Models\LegalSeasonOccurrence;
use App\Domains\Legal\Models\LegalSeasonOverride;
use App\Domains\Legal\Services\PeriodAvailabilityEvaluator;
use App\Domains\Legal\Support\SeasonDateRange;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Operations\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\InteractsWithAccessControl;
use Tests\Support\LegalFixtures;

uses(InteractsWithAccessControl::class, RefreshDatabase::class);

function fictionalPublishedSpecies(): Species
{
    return Species::factory()->published()->create([
        'scientific_name' => 'Testus calendarus',
    ]);
}

it('lets a legal editor create a recurring season and rejects april 31', function (): void {
    $editor = $this->createUserWithRole(Role::LegalEditor);
    $stack = LegalFixtures::approvedCitationStack($editor);
    $species = fictionalPublishedSpecies();
    $rule = LegalFixtures::publishedRule($stack['provision'], $editor, LegalRuleEffect::Allow, LegalActivityType::Hunting, $species->id, 'FICTIONAL hunt allow');

    $this->actingAs($editor, 'web')->postJson('/api/v1/admin/legal/seasons', [
        'legal_rule_id' => $rule->public_id,
        'species_id' => $species->public_id,
        'activity_type' => 'hunting',
        'season_type' => SeasonType::Opening->value,
        'schedule_type' => SeasonScheduleType::AnnualRecurring->value,
        'start_month' => 9,
        'start_day' => 1,
        'end_month' => 11,
        'end_day' => 30,
        'jurisdiction_code' => 'GE',
    ])->assertCreated()->assertJsonPath('data.status', 'draft');

    $this->actingAs($editor, 'web')->postJson('/api/v1/admin/legal/seasons', [
        'legal_rule_id' => $rule->public_id,
        'species_id' => $species->public_id,
        'activity_type' => 'hunting',
        'season_type' => SeasonType::Opening->value,
        'schedule_type' => SeasonScheduleType::AnnualRecurring->value,
        'start_month' => 4,
        'start_day' => 31,
        'end_month' => 5,
        'end_day' => 1,
        'jurisdiction_code' => 'GE',
    ])->assertStatus(422);
});

it('publishes a season only with a published cited rule and generates occurrences idempotently', function (): void {
    $admin = $this->createAdmin();
    $stack = LegalFixtures::approvedCitationStack($admin);
    $species = fictionalPublishedSpecies();
    $definition = LegalFixtures::publishedSeason($stack['provision'], $admin, $species->id, LegalActivityType::Hunting, [
        'start_month' => 9,
        'start_day' => 1,
        'end_month' => 11,
        'end_day' => 30,
        'first_season_year' => 2025,
        'last_season_year' => 2027,
        'jurisdiction_code' => 'GE',
    ]);

    expect($definition->status)->toBe(LegalRuleStatus::Published);
    $first = LegalSeasonOccurrence::query()->where('season_definition_id', $definition->id)->where('is_current', true)->count();
    expect($first)->toBeGreaterThan(0);

    $this->actingAs($admin, 'web')
        ->postJson('/api/v1/admin/legal/seasons/'.$definition->public_id.'/generate-occurrences')
        ->assertOk();
    $second = LegalSeasonOccurrence::query()->where('season_definition_id', $definition->id)->where('is_current', true)->count();
    expect($second)->toBe($first);
    expect(LegalSeasonOccurrence::query()->where('season_definition_id', $definition->id)->where('is_current', true)->pluck('season_year')->unique()->count())->toBe($first);
});

it('hides draft seasons from public availability and returns unknown without evidence', function (): void {
    $admin = $this->createAdmin();
    $stack = LegalFixtures::approvedCitationStack($admin);
    $species = fictionalPublishedSpecies();
    $rule = LegalFixtures::publishedRule($stack['provision'], $admin, LegalRuleEffect::Allow, LegalActivityType::Hunting, $species->id);
    LegalSeasonDefinition::factory()->create([
        'legal_rule_id' => $rule->id,
        'species_id' => $species->id,
        'activity_type' => LegalActivityType::Hunting,
        'status' => LegalRuleStatus::Draft,
        'jurisdiction_code' => 'GE',
        'start_month' => 1,
        'start_day' => 1,
        'end_month' => 12,
        'end_day' => 31,
    ]);

    $this->getJson('/api/v1/outdoor/availability?activity=hunting&from=2026-09-01&to=2026-09-07&mode=any_date')
        ->assertOk()
        ->assertJsonPath('data.results', []);

    $this->getJson('/api/v1/species/'.$species->canonical_slug.'/seasons?from=2026-09-01&to=2026-09-07')
        ->assertOk()
        ->assertJsonPath('data.availability.overall_state', CalendarAvailabilityState::Unknown->value);
});

it('classifies partial overlap as partially_open and entire_period as not open', function (): void {
    $admin = $this->createAdmin();
    $stack = LegalFixtures::approvedCitationStack($admin);
    $species = fictionalPublishedSpecies();
    LegalFixtures::publishedSeason($stack['provision'], $admin, $species->id, LegalActivityType::Hunting, [
        'schedule_type' => SeasonScheduleType::FixedRange,
        'start_date' => '2026-09-10',
        'end_date' => '2026-09-20',
        'start_month' => null,
        'start_day' => null,
        'end_month' => null,
        'end_day' => null,
        'first_season_year' => null,
        'last_season_year' => null,
        'jurisdiction_code' => 'GE',
    ]);

    $any = $this->getJson('/api/v1/outdoor/availability?activity=hunting&from=2026-09-01&to=2026-09-30&mode=any_date&species='.$species->canonical_slug)
        ->assertOk();
    expect($any->json('data.results.0.overall_state'))->toBe(CalendarAvailabilityState::PartiallyOpen->value)
        ->and($any->json('data.results.0.available_windows'))->not->toBeEmpty();

    $entire = $this->getJson('/api/v1/outdoor/availability?activity=hunting&from=2026-09-01&to=2026-09-30&mode=entire_period&species='.$species->canonical_slug)
        ->assertOk();
    expect($entire->json('data.results.0.overall_state'))->not->toBe(CalendarAvailabilityState::Open->value);
});

it('returns closed only when a published closure covers the period', function (): void {
    $admin = $this->createAdmin();
    $stack = LegalFixtures::approvedCitationStack($admin);
    $species = fictionalPublishedSpecies();
    $opening = LegalFixtures::publishedSeason($stack['provision'], $admin, $species->id, LegalActivityType::Hunting, [
        'schedule_type' => SeasonScheduleType::FixedRange,
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-30',
        'start_month' => null,
        'start_day' => null,
        'end_month' => null,
        'end_day' => null,
        'jurisdiction_code' => 'GE',
    ]);
    $closureRule = LegalFixtures::publishedRule($stack['provision'], $admin, LegalRuleEffect::Prohibit, LegalActivityType::Hunting, $species->id, 'FICTIONAL closure');
    $start = SeasonDateRange::fromInclusiveDates('2026-09-01', '2026-09-30', 'Asia/Tbilisi');
    $override = LegalSeasonOverride::factory()->create([
        'base_season_definition_id' => $opening->id,
        'legal_rule_id' => $closureRule->id,
        'override_type' => SeasonOverrideType::Closure,
        'starts_at' => Carbon::createFromInterface($start->startsAt)->utc(),
        'ends_at_exclusive' => Carbon::createFromInterface($start->endsAtExclusive)->utc(),
        'jurisdiction_code' => 'GE',
        'status' => LegalRuleStatus::Published,
        'published_at' => now(),
        'precedence' => 200,
        'reason' => 'FICTIONAL temporary closure',
    ]);
    expect($override->status)->toBe(LegalRuleStatus::Published);

    $response = $this->getJson('/api/v1/outdoor/availability?activity=hunting&from=2026-09-01&to=2026-09-30&mode=entire_period&species='.$species->canonical_slug)
        ->assertOk();
    expect($response->json('data.results.0.overall_state'))->toBe(CalendarAvailabilityState::Closed->value);
});

it('enforces the public maximum range and rejects inverted dates', function (): void {
    $this->getJson('/api/v1/outdoor/availability?activity=hunting&from=2026-01-01&to=2028-01-01')
        ->assertStatus(422);
    $this->getJson('/api/v1/outdoor/availability?activity=hunting&from=2026-09-10&to=2026-09-01')
        ->assertStatus(422);
});

it('audits season creation and blocks guests from admin season routes', function (): void {
    $editor = $this->createUserWithRole(Role::LegalEditor);
    $stack = LegalFixtures::approvedCitationStack($editor);
    $species = fictionalPublishedSpecies();
    $rule = LegalFixtures::publishedRule($stack['provision'], $editor, LegalRuleEffect::Allow, LegalActivityType::Hunting, $species->id, 'FICTIONAL hunt allow');

    $this->actingAs($editor, 'web')->postJson('/api/v1/admin/legal/seasons', [
        'legal_rule_id' => $rule->public_id,
        'species_id' => $species->public_id,
        'activity_type' => 'hunting',
        'season_type' => SeasonType::Opening->value,
        'schedule_type' => SeasonScheduleType::AnnualRecurring->value,
        'start_month' => 10,
        'start_day' => 1,
        'end_month' => 12,
        'end_day' => 31,
        'jurisdiction_code' => 'GE',
    ])->assertCreated();

    expect(AuditLog::query()->where('event', AuditEvent::LegalSeasonCreated->value)->count())->toBe(1);
});

it('forbids catalog managers and guests from admin season routes', function (): void {
    $manager = $this->createUserWithRole(Role::CatalogManager);
    $this->actingAs($manager, 'web')->getJson('/api/v1/admin/legal/seasons')->assertForbidden();
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/v1/admin/legal/seasons')->assertUnauthorized();
});

it('isolates superseded occurrences from public results', function (): void {
    $admin = $this->createAdmin();
    $stack = LegalFixtures::approvedCitationStack($admin);
    $species = fictionalPublishedSpecies();
    $definition = LegalFixtures::publishedSeason($stack['provision'], $admin, $species->id, LegalActivityType::Hunting, [
        'schedule_type' => SeasonScheduleType::FixedRange,
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-30',
        'start_month' => null,
        'start_day' => null,
        'end_month' => null,
        'end_day' => null,
        'jurisdiction_code' => 'GE',
    ]);

    $this->actingAs($admin, 'web')
        ->postJson('/api/v1/admin/legal/seasons/'.$definition->public_id.'/supersede', ['reason' => 'Replaced by later notice'])
        ->assertOk();

    $this->getJson('/api/v1/outdoor/availability?activity=hunting&from=2026-09-01&to=2026-09-07&species='.$species->canonical_slug)
        ->assertOk()
        ->assertJsonPath('data.results.0.overall_state', CalendarAvailabilityState::Unknown->value);
});

it('serves calendar months and upcoming transitions from published occurrences', function (): void {
    $admin = $this->createAdmin();
    $stack = LegalFixtures::approvedCitationStack($admin);
    $species = fictionalPublishedSpecies();
    LegalFixtures::publishedSeason($stack['provision'], $admin, $species->id, LegalActivityType::Hunting, [
        'schedule_type' => SeasonScheduleType::FixedRange,
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-30',
        'start_month' => null,
        'start_day' => null,
        'end_month' => null,
        'end_day' => null,
        'jurisdiction_code' => 'GE',
    ]);

    $this->getJson('/api/v1/outdoor/calendar?activity=hunting&month=2026-09')
        ->assertOk()
        ->assertJsonPath('data.activity', 'hunting');
    expect($this->getJson('/api/v1/outdoor/calendar?activity=hunting&month=2026-09')->json('data.occurrences'))->not->toBeEmpty();

    $this->getJson('/api/v1/outdoor/season-transitions?activity=hunting&from=2026-08-20&days=20')
        ->assertOk()
        ->assertJsonPath('data.disclaimer', 'legal.informational_not_advice');
});

it('builds a timeline with merged adjacent open segments', function (): void {
    $admin = $this->createAdmin();
    $stack = LegalFixtures::approvedCitationStack($admin);
    $species = fictionalPublishedSpecies();
    LegalFixtures::publishedSeason($stack['provision'], $admin, $species->id, LegalActivityType::Hunting, [
        'schedule_type' => SeasonScheduleType::FixedRange,
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-15',
        'start_month' => null,
        'start_day' => null,
        'end_month' => null,
        'end_day' => null,
        'jurisdiction_code' => 'GE',
    ]);

    $query = new PeriodAvailabilityQueryData(
        activityType: LegalActivityType::Hunting,
        period: SeasonDateRange::fromQueryDates('2026-09-01', '2026-09-15', 'Asia/Tbilisi'),
        mode: AvailabilityMode::Timeline,
        jurisdictionCode: 'GE',
        speciesId: $species->id,
        includeTrace: true,
    );
    $result = app(PeriodAvailabilityEvaluator::class)->compute($query, 'en', 1, 10);
    expect($result['results'][0]['overall_state'])->toBe(CalendarAvailabilityState::Open->value)
        ->and($result['results'][0]['timeline'])->not->toBeEmpty();
});
