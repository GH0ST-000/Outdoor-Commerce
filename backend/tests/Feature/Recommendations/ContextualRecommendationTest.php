<?php

declare(strict_types=1);

use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Search\Contracts\SearchGateway;
use App\Domains\Identity\Enums\Role;
use App\Domains\Identity\Models\User;
use App\Domains\Legal\Actions\VerifyOutdoorContextTokenAction;
use App\Domains\Legal\DTOs\DerivedLegalContextData;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Operations\Models\AuditLog;
use App\Domains\Recommendations\Enums\AssignmentSourceType;
use App\Domains\Recommendations\Enums\AssignmentStatus;
use App\Domains\Recommendations\Enums\AssignmentType;
use App\Domains\Recommendations\Models\ContextTaxonomyTerm;
use App\Domains\Recommendations\Models\ProductContextAssignment;
use App\Domains\Recommendations\Models\RecommendationProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\FakeSearchGateway;
use Tests\Support\InteractsWithAccessControl;
use Tests\Support\PublicCatalogFixtures;

uses(InteractsWithAccessControl::class);

it('ranks an allowed context deterministically and explains every result', function (): void {
    $exact = PublicCatalogFixtures::publicProduct(['ka_slug' => 'optics-exact', 'sku' => 'OPT-1', 'price' => 20000]);
    $broad = PublicCatalogFixtures::publicProduct(['ka_slug' => 'optics-broad', 'sku' => 'OPT-2', 'price' => 10000]);
    assign($exact['product']->id, 'activity', 'hunting', AssignmentType::Supported);
    assign($exact['product']->id, 'species', 'roe-deer', AssignmentType::PreferredMatch);
    assign($broad['product']->id, 'activity', 'hunting', AssignmentType::Supported);

    $response = $this->postJson('/api/v1/recommendations/contextual', [
        'placement' => 'map_location_result',
        'context_token' => token([
            'conclusion' => 'allowed',
            'spatially_verified' => true,
            'activity' => 'hunting',
            'species_slug' => 'roe-deer',
            'completeness' => 'high',
        ]),
        'locale' => 'ka',
        'currency' => 'GEL',
    ])->assertOk();

    $slugs = array_column($response->json('data.recommendations'), 'slug');
    expect($response->json('data.gate'))->toBe('recommendations_allowed')
        ->and($slugs[0])->toBe('optics-exact')
        ->and($response->json('data.recommendations.0.primary_reason_text'))->not->toBe('')
        ->and($response->json('data.recommendations.0.confidence'))->not->toBe('insufficient')
        ->and($response->json('data.disclaimer'))->not->toBe('');
});

it('removes prohibited equipment and ignores a boost or pin', function (): void {
    $banned = PublicCatalogFixtures::publicProduct(['ka_slug' => 'ammo-pack', 'sku' => 'AMMO-1']);
    $safe = PublicCatalogFixtures::publicProduct(['ka_slug' => 'binoculars', 'sku' => 'BIN-1']);
    assign($banned['product']->id, 'activity', 'hunting', AssignmentType::PreferredMatch);
    assign($banned['product']->id, 'equipment', 'ammunition', AssignmentType::PreferredMatch);
    assign($safe['product']->id, 'activity', 'hunting', AssignmentType::Supported);
    $admin = $this->createAdmin();
    $this->actingAs($admin, 'web')->postJson('/api/v1/admin/recommendations/merchandising-rules', [
        'name' => 'Boost banned',
        'placement' => 'map_location_result',
        'product_id' => $banned['product']->id,
        'adjustment_type' => 'boost',
        'adjustment_value' => 12,
        'priority' => 1,
        'paid_placement' => true,
        'reason' => 'Campaign',
        'starts_at' => now()->subHour()->toIso8601String(),
        'ends_at' => now()->addDay()->toIso8601String(),
    ])->assertCreated();
    $this->actingAs($admin, 'web')->postJson('/api/v1/admin/recommendations/merchandising-rules', [
        'name' => 'Pin banned',
        'placement' => 'map_location_result',
        'product_id' => $banned['product']->id,
        'adjustment_type' => 'pin',
        'adjustment_value' => 1,
        'priority' => 1,
        'reason' => 'Pin attempt',
        'starts_at' => now()->subHour()->toIso8601String(),
        'ends_at' => now()->addDay()->toIso8601String(),
    ])->assertCreated();

    $response = $this->postJson('/api/v1/recommendations/contextual', [
        'placement' => 'map_location_result',
        'context_token' => token([
            'conclusion' => 'allowed',
            'spatially_verified' => true,
            'activity' => 'hunting',
            'prohibited_equipment' => ['ammunition'],
            'completeness' => 'high',
        ]),
        'locale' => 'ka',
    ])->assertOk();

    $slugs = array_column($response->json('data.recommendations'), 'slug');
    expect($slugs)->toContain('binoculars')->not->toContain('ammo-pack');
});

it('blocks prohibited and conflict contexts and does not call unknown legal', function (): void {
    $product = PublicCatalogFixtures::publicProduct(['ka_slug' => 'calling-horn', 'sku' => 'HORN-1']);
    assign($product['product']->id, 'activity', 'hunting', AssignmentType::PreferredMatch);

    $blocked = $this->postJson('/api/v1/recommendations/contextual', [
        'placement' => 'outdoor_context_result',
        'context_token' => token(['conclusion' => 'prohibited', 'spatially_verified' => true, 'activity' => 'hunting', 'completeness' => 'high']),
    ])->assertOk();
    $conflict = $this->postJson('/api/v1/recommendations/contextual', [
        'placement' => 'outdoor_context_result',
        'context_token' => token(['conclusion' => 'conflict', 'spatially_verified' => true, 'activity' => 'hunting', 'completeness' => 'high']),
    ])->assertOk();
    $unknown = $this->postJson('/api/v1/recommendations/contextual', [
        'placement' => 'species_detail',
        'activity' => 'hunting',
        'species_slug' => 'roe-deer',
        'locale' => 'ka',
    ])->assertOk();

    expect($blocked->json('data.recommendations'))->toBe([])
        ->and($blocked->json('data.gate'))->toBe('recommendations_blocked')
        ->and($conflict->json('data.recommendations'))->toBe([])
        ->and($conflict->json('data.warnings.0.code'))->toBe('gate_conflict')
        ->and($unknown->json('data.gate'))->toBe('recommendations_unknown')
        ->and($unknown->json('data.context.framing'))->toBe('species_related')
        ->and(json_encode($unknown->json('data')))->not->toContain('recommendations_allowed');
});

it('rejects a client-forged gate and keeps coordinates out of the public contract', function (): void {
    $this->postJson('/api/v1/recommendations/contextual', [
        'placement' => 'map_location_result',
        'gate' => 'recommendations_allowed',
        'activity' => 'hunting',
    ])->assertStatus(422);

    $this->postJson('/api/v1/recommendations/contextual', [
        'placement' => 'map_location_result',
        'lng' => 44.8,
        'lat' => 41.7,
        'activity' => 'hunting',
    ])->assertStatus(422);
});

it('hides unpublished, draft mappings, and out-of-stock products', function (): void {
    $draftProduct = PublicCatalogFixtures::publicProduct(['ka_slug' => 'draft-scope', 'sku' => 'DRF-1', 'status' => ProductStatus::Draft]);
    $stocked = PublicCatalogFixtures::publicProduct(['ka_slug' => 'stocked-scope', 'sku' => 'STK-1']);
    $empty = PublicCatalogFixtures::publicProduct(['ka_slug' => 'empty-scope', 'sku' => 'EMP-1', 'stock' => 0]);
    assign($draftProduct['product']->id, 'activity', 'hunting', AssignmentType::PreferredMatch);
    assign($stocked['product']->id, 'activity', 'hunting', AssignmentType::PreferredMatch, AssignmentStatus::Draft);
    assign($empty['product']->id, 'activity', 'hunting', AssignmentType::PreferredMatch);
    assign($stocked['product']->id, 'activity', 'hunting', AssignmentType::Supported);

    $slugs = $this->postJson('/api/v1/recommendations/contextual', [
        'placement' => 'map_location_result',
        'context_token' => token(['conclusion' => 'allowed', 'spatially_verified' => true, 'activity' => 'hunting', 'completeness' => 'high']),
        'locale' => 'ka',
    ])->assertOk()->json('data.recommendations');

    $names = array_column($slugs, 'slug');
    expect($names)->toContain('stocked-scope')
        ->and($names)->not->toContain('draft-scope')
        ->and($names)->not->toContain('empty-scope')
        ->and($slugs[0]['add_to_cart_eligible'])->toBeTrue();
});

it('localizes reasons and paginates', function (): void {
    foreach (['one', 'two', 'three'] as $index => $name) {
        $product = PublicCatalogFixtures::publicProduct([
            'ka_slug' => 'page-ka-'.$name,
            'en_slug' => 'page-en-'.$name,
            'sku' => 'PG-'.$index,
        ]);
        assign($product['product']->id, 'activity', 'fishing', AssignmentType::PreferredMatch);
    }

    $english = $this->postJson('/api/v1/recommendations/contextual', [
        'placement' => 'season_explorer',
        'activity' => 'fishing',
        'locale' => 'en',
        'per_page' => 1,
        'page' => 1,
    ])->assertOk();
    $georgian = $this->withHeader('X-Locale', 'ka')->postJson('/api/v1/recommendations/contextual', [
        'placement' => 'season_explorer',
        'activity' => 'fishing',
        'locale' => 'ka',
        'per_page' => 1,
        'page' => 2,
    ])->assertOk();

    $englishCodes = array_column($english->json('data.recommendations.0.supporting_reasons'), 'code');
    expect($english->json('data.recommendations.0.primary_reason'))->toBe('species_related_unlocated')
        ->and($englishCodes)->toContain('activity_match')
        ->and($georgian->json('data.recommendations.0.primary_reason_text'))->not->toBe($english->json('data.recommendations.0.primary_reason_text'))
        ->and($georgian->json('data.pagination.page'))->toBe(2)
        ->and($georgian->json('data.recommendations.0.slug'))->not->toBe($english->json('data.recommendations.0.slug'));
});

it('requires review before publication and blocks self-approval', function (): void {
    $author = $this->createAdmin();
    $reviewer = $this->createAdmin();
    $payload = profilePayload('Experimental ranking');

    $created = $this->actingAs($author, 'web')->postJson('/api/v1/admin/recommendations/profiles', $payload)->assertCreated();
    $id = $created->json('data.public_id');
    $this->actingAs($author, 'web')->postJson('/api/v1/admin/recommendations/profiles/'.$id.'/submit-review')->assertOk();
    asUser($this, $author)->postJson('/api/v1/admin/recommendations/profiles/'.$id.'/approve')->assertStatus(409);
    asUser($this, $reviewer)->postJson('/api/v1/admin/recommendations/profiles/'.$id.'/approve')->assertOk();
    asUser($this, $author)->postJson('/api/v1/admin/recommendations/profiles/'.$id.'/publish')->assertStatus(409);
    asUser($this, $reviewer)->postJson('/api/v1/admin/recommendations/profiles/'.$id.'/publish')->assertOk()
        ->assertJsonPath('data.status', 'published');

    expect(RecommendationProfile::query()->where('placement', 'species_detail')->where('status', 'published')->count())->toBe(1);
});

it('rejects an excessive boost and an invalid profile', function (): void {
    $admin = $this->createAdmin();
    $this->actingAs($admin, 'web')->postJson('/api/v1/admin/recommendations/merchandising-rules', [
        'name' => 'Too big',
        'placement' => 'species_detail',
        'adjustment_type' => 'boost',
        'adjustment_value' => 12,
        'reason' => 'No',
        'starts_at' => now()->subHour()->toIso8601String(),
        'ends_at' => now()->addDay()->toIso8601String(),
    ])->assertCreated();

    $payload = profilePayload('Broken');
    $payload['weights']['activity_match'] = 50;
    $payload['configuration']['weights']['activity_match'] = 50;
    $this->actingAs($admin, 'web')->postJson('/api/v1/admin/recommendations/profiles', $payload)->assertStatus(422);

    $this->actingAs($admin, 'web')->postJson('/api/v1/admin/recommendations/merchandising-rules', [
        'name' => 'Way too big',
        'placement' => 'species_detail',
        'adjustment_type' => 'boost',
        'adjustment_value' => 40,
        'reason' => 'No',
        'starts_at' => now()->subHour()->toIso8601String(),
        'ends_at' => now()->addDay()->toIso8601String(),
    ])->assertStatus(422);
});

it('authorizes assignment management, audits it, and simulates exclusions', function (): void {
    $admin = $this->createAdmin();
    $manager = $this->createUserWithRole(Role::CatalogManager);
    $product = PublicCatalogFixtures::publicProduct(['ka_slug' => 'mapped-rod', 'sku' => 'ROD-1']);
    $term = ContextTaxonomyTerm::query()->where('dimension', 'activity')->where('code', 'fishing')->firstOrFail();

    asUser($this, $manager)->postJson('/api/v1/admin/recommendations/profiles/'.RecommendationProfile::query()->firstOrFail()->public_id.'/publish')
        ->assertForbidden();

    $created = asUser($this, $admin)->postJson('/api/v1/admin/products/'.$product['product']->id.'/context-assignments', [
        'term_id' => $term->public_id,
        'assignment_type' => 'preferred_match',
        'source_type' => 'manual_verified',
        'status' => 'active',
    ])->assertCreated();

    expect(AuditLog::query()->where('event', AuditEvent::RecommendationAssignmentSaved)->exists())->toBeTrue();

    asUser($this, $admin)->postJson('/api/v1/admin/recommendations/bulk-assignments', [
        'product_ids' => [$product['product']->id],
        'confirm' => true,
        'term_id' => $term->public_id,
        'assignment_type' => 'preferred_match',
        'source_type' => 'manual_verified',
        'status' => 'active',
    ])->assertStatus(202);

    $simulation = asUser($this, $admin)->postJson('/api/v1/admin/recommendations/simulate', [
        'placement' => 'species_detail',
        'conclusion' => 'conditional',
        'activity' => 'fishing',
        'completeness' => 'partial',
        'locale' => 'en',
    ])->assertOk();

    expect($simulation->json('data.gate'))->toBe('recommendations_information_only')
        ->and($simulation->json('data.diagnostics.rows'))->not->toBeEmpty()
        ->and($created->json('data.public_id'))->not->toBeEmpty();

    asUser($this, $admin)->getJson('/api/v1/admin/recommendations/coverage')->assertOk()
        ->assertJsonStructure(['data' => ['products_without_activity', 'active_profiles']]);
});

it('keeps results when meilisearch is unavailable', function (): void {
    $gateway = $this->app->make(SearchGateway::class);
    expect($gateway)->toBeInstanceOf(FakeSearchGateway::class);
    $gateway->healthy = false;
    $product = PublicCatalogFixtures::publicProduct(['ka_slug' => 'fallback-reel', 'sku' => 'REEL-1']);
    assign($product['product']->id, 'activity', 'fishing', AssignmentType::PreferredMatch);

    $this->postJson('/api/v1/recommendations/contextual', [
        'placement' => 'season_explorer',
        'activity' => 'fishing',
        'locale' => 'ka',
    ])->assertOk()->assertJsonPath('data.recommendations.0.slug', 'fallback-reel');
});

it('rate limits the public recommendation endpoint', function (): void {
    $this->flushApplicationCacheSafely();
    config(['recommendations.rate_limits.public_per_minute' => 2]);
    $body = ['placement' => 'species_detail', 'activity' => 'hunting'];
    $this->postJson('/api/v1/recommendations/contextual', $body)->assertOk();
    $this->postJson('/api/v1/recommendations/contextual', $body)->assertOk();
    $this->postJson('/api/v1/recommendations/contextual', $body)->assertStatus(429);
});

it('reports a bounded query count for a small fictional catalog', function (): void {
    $ids = [];
    foreach (range(1, 12) as $index) {
        $product = PublicCatalogFixtures::publicProduct(['ka_slug' => 'perf-'.$index, 'sku' => 'PERF-'.$index]);
        assign($product['product']->id, 'activity', 'hunting', AssignmentType::Supported);
        $ids[] = $product['product']->id;
    }
    DB::flushQueryLog();
    DB::enableQueryLog();
    $started = microtime(true);
    $this->postJson('/api/v1/recommendations/contextual', [
        'placement' => 'map_location_result',
        'context_token' => token(['conclusion' => 'allowed', 'spatially_verified' => true, 'activity' => 'hunting', 'completeness' => 'high']),
        'locale' => 'en',
        'per_page' => 10,
    ])->assertOk();
    $elapsed = (int) round((microtime(true) - $started) * 1000);
    $queries = count(DB::getQueryLog());
    fwrite(STDERR, "RECOMMENDATION_PERF products=12 queries={$queries} ms={$elapsed}\n");

    expect($queries)->toBeLessThan(250)->and(count($ids))->toBe(12);
});

function asUser(object $test, User $user): object
{
    app('auth')->forgetGuards();
    $test->flushSession();
    $test->actingAs($user, 'web');

    return $test;
}

function assign(int $productId, string $dimension, string $code, AssignmentType $type, AssignmentStatus $status = AssignmentStatus::Active): void
{
    $term = ContextTaxonomyTerm::query()->firstOrCreate(
        ['dimension' => $dimension, 'code' => $code],
        [
            'public_id' => (string) Str::uuid(),
            'default_label' => $code,
            'is_active' => true,
            'sort_order' => 0,
        ],
    );
    ProductContextAssignment::query()->create([
        'public_id' => (string) Str::uuid(),
        'product_id' => $productId,
        'variant_key' => 0,
        'context_taxonomy_term_id' => $term->id,
        'assignment_type' => $type,
        'weight' => 1,
        'source_type' => AssignmentSourceType::ManualVerified,
        'status' => $status,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function token(array $overrides): string
{
    $context = DerivedLegalContextData::fromArray(array_merge([
        'conclusion' => 'unknown',
        'boundary_uncertain' => false,
        'spatially_verified' => false,
        'activity' => 'hunting',
        'completeness' => 'partial',
    ], $overrides));

    return app(VerifyOutdoorContextTokenAction::class)->sign($context, now()->addMinutes(10)->getTimestamp());
}

/**
 * @return array<string, mixed>
 */
function profilePayload(string $name): array
{
    $weights = config('recommendations.default_weights');

    return [
        'name' => $name,
        'placement' => 'species_detail',
        'weights' => $weights,
        'configuration' => [
            'minimum_score' => 35,
            'maximum_results' => 8,
            'candidate_limit' => 80,
            'merchandising_max_points' => 8,
            'activity_only_cap' => 60,
            'stock_behavior' => 'hide_out_of_stock',
            'backorder_behavior' => 'unsupported',
            'tie_break' => config('recommendations.tie_break'),
            'weights' => $weights,
        ],
    ];
}
