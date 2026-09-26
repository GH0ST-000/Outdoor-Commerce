<?php

declare(strict_types=1);

use App\Domains\Geography\Enums\SpatialAssignmentType;
use App\Domains\Geography\Enums\SpatialReviewStatus;
use App\Domains\Geography\Models\SpatialDatasetVersion;
use App\Domains\Geography\Models\SpatialZone;
use App\Domains\Geography\Models\SpatialZoneGeometryVersion;
use App\Domains\Geography\Support\MultipolygonGeometry;
use App\Domains\Hunting\Models\Species;
use App\Domains\Identity\Enums\Role;
use App\Domains\Legal\Enums\LegalActivityType;
use App\Domains\Legal\Enums\LegalConclusion;
use App\Domains\Legal\Enums\LegalRuleEffect;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Operations\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithAccessControl;
use Tests\Support\LegalFixtures;
use Tests\Support\SpatialFixtures;

uses(InteractsWithAccessControl::class, RefreshDatabase::class);

beforeEach(function (): void {
    SpatialFixtures::fakeDisk();
});

it('rejects unauthorized spatial admin access', function (): void {
    $this->getJson('/api/v1/admin/spatial/sources')->assertUnauthorized();
    $customer = $this->createUserWithRole(Role::CatalogManager);
    $this->actingAs($customer, 'web')->getJson('/api/v1/admin/spatial/sources')->assertForbidden();
});

it('walks source verification, geojson import, publication, and location evaluation', function (): void {
    $admin = $this->createAdmin();
    $editor = $this->createUserWithRole(Role::LegalEditor);
    $stack = LegalFixtures::approvedCitationStack($admin);
    $rule = SpatialFixtures::publishedHuntingRule($stack['provision'], $admin);

    $this->actingAs($editor, 'web')->postJson('/api/v1/admin/spatial/sources', [
        'name' => 'FICTIONAL Test Spatial Agency',
        'source_type' => 'manual_verified_dataset',
        'publisher_name' => 'FICTIONAL Publisher',
        'jurisdiction_code' => 'XX',
        'attribution_text' => 'FICTIONAL test source',
        'is_fictional' => true,
    ])->assertCreated()->assertJsonPath('data.verification_status', 'unverified');

    $sourceId = $this->actingAs($editor, 'web')->getJson('/api/v1/admin/spatial/sources')->json('data.0.id');

    $this->actingAs($editor, 'web')->postJson('/api/v1/admin/spatial/sources/'.$sourceId.'/verify')
        ->assertOk()
        ->assertJsonPath('data.verification_status', 'verified');

    $datasetId = $this->actingAs($editor, 'web')->postJson('/api/v1/admin/spatial/datasets', [
        'spatial_source_id' => $sourceId,
        'name' => 'FICTIONAL Protected Areas',
        'dataset_type' => 'protected_areas',
        'jurisdiction_code' => 'XX',
        'is_fictional' => true,
    ])->assertCreated()->json('data.id');

    $upload = $this->actingAs($editor, 'web')->post('/api/v1/admin/spatial/datasets/'.$datasetId.'/versions', [
        'file' => SpatialFixtures::geoJsonUpload(SpatialFixtures::squareGeoJson()),
        'version_label' => 'v1-test',
        'source_crs' => 'EPSG:4326',
    ], ['Accept' => 'application/json']);
    $upload->assertCreated()->assertJsonPath('data.review_status', 'draft');
    $versionId = $upload->json('data.id');
    expect($upload->json('data.storage_path') ?? null)->toBeNull();

    $this->actingAs($editor, 'web')->postJson('/api/v1/admin/spatial/dataset-versions/'.$versionId.'/map', [
        'external_identifier' => 'code',
        'name' => 'title',
    ])->assertOk();

    $this->actingAs($editor, 'web')->postJson('/api/v1/admin/spatial/dataset-versions/'.$versionId.'/import')
        ->assertOk()
        ->assertJsonPath('data.status', 'imported');

    expect(SpatialZone::query()->count())->toBe(1)
        ->and(SpatialZoneGeometryVersion::query()->where('status', 'draft')->count())->toBe(1);

    $this->actingAs($editor, 'web')->postJson('/api/v1/admin/spatial/dataset-versions/'.$versionId.'/submit-review')->assertOk();
    $this->actingAs($editor, 'web')->postJson('/api/v1/admin/spatial/dataset-versions/'.$versionId.'/approve')->assertOk();
    $this->actingAs($editor, 'web')->postJson('/api/v1/admin/spatial/dataset-versions/'.$versionId.'/publish')->assertForbidden();

    $this->app['auth']->forgetGuards();
    $this->actingAs($admin, 'web')->postJson('/api/v1/admin/spatial/dataset-versions/'.$versionId.'/publish')
        ->assertOk()
        ->assertJsonPath('data.review_status', 'published');

    $zoneId = SpatialZone::query()->firstOrFail()->public_id;
    $this->actingAs($admin, 'web')->postJson('/api/v1/admin/spatial/zones/'.$zoneId.'/legal-rules', [
        'legal_rule_id' => $rule->public_id,
        'assignment_type' => SpatialAssignmentType::ProhibitedWithin->value,
        'precedence' => 10,
    ])->assertCreated();

    $inside = $this->getJson('/api/v1/spatial/evaluate?lng=44.5&lat=41.5&activity=hunting&jurisdiction=XX');
    $inside->assertOk()->assertJsonPath('data.outcome', LegalConclusion::Prohibited->value);
    expect($inside->json('data.matching_zones'))->not->toBeEmpty();

    $outside = $this->getJson('/api/v1/spatial/evaluate?lng=10&lat=10&activity=hunting&jurisdiction=XX');
    $outside->assertOk()->assertJsonPath('data.outcome', LegalConclusion::Unknown->value);

    $near = $this->getJson('/api/v1/spatial/evaluate?lng=43.9995&lat=41.5&activity=hunting&jurisdiction=XX');
    $near->assertOk()->assertJsonPath('data.boundary_warning', true);

    $this->getJson('/api/v1/spatial/zones?bbox=43.5,40.5,45.5,42.5')->assertOk()->assertJsonPath('data.features.0.properties.is_fictional', true);
    $this->getJson('/api/v1/spatial/zones/'.$zoneId)->assertOk()->assertJsonMissingPath('data.storage_path');

    expect(AuditLog::query()->where('event', AuditEvent::SpatialDatasetVersionPublished->value)->exists())->toBeTrue();
});

it('rejects invalid geojson, path traversal filenames, and oversized feature counts', function (): void {
    $admin = $this->createAdmin();
    $source = SpatialFixtures::publishedZone($admin)['source'];
    $datasetId = $this->actingAs($admin, 'web')->postJson('/api/v1/admin/spatial/datasets', [
        'spatial_source_id' => $source->public_id,
        'name' => 'FICTIONAL Reject Dataset',
        'dataset_type' => 'other',
        'jurisdiction_code' => 'XX',
        'is_fictional' => true,
    ])->json('data.id');

    $this->actingAs($admin, 'web')->post('/api/v1/admin/spatial/datasets/'.$datasetId.'/versions', [
        'file' => SpatialFixtures::geoJsonUpload('{', 'broken.geojson'),
        'version_label' => 'bad-json',
        'source_crs' => 'EPSG:4326',
    ], ['Accept' => 'application/json'])->assertStatus(422);

    $this->actingAs($admin, 'web')->post('/api/v1/admin/spatial/datasets/'.$datasetId.'/versions', [
        'file' => SpatialFixtures::geoJsonUpload(SpatialFixtures::squareGeoJson(), '..evil.geojson'),
        'version_label' => 'traversal',
        'source_crs' => 'EPSG:4326',
    ], ['Accept' => 'application/json'])->assertStatus(422);

    $this->actingAs($admin, 'web')->post('/api/v1/admin/spatial/datasets/'.$datasetId.'/versions', [
        'file' => SpatialFixtures::geoJsonUpload(SpatialFixtures::squareGeoJson()),
        'version_label' => 'bad-crs',
        'source_crs' => 'EPSG:32638',
    ], ['Accept' => 'application/json'])->assertStatus(422);
});

it('keeps draft geometry out of public APIs and does not auto-publish imports', function (): void {
    $admin = $this->createAdmin();
    $published = SpatialFixtures::publishedZone($admin);
    $draft = SpatialZone::factory()->create([
        'spatial_dataset_id' => $published['dataset']->id,
        'status' => 'draft',
        'is_fictional' => true,
        'default_name' => 'FICTIONAL Draft Zone',
    ]);

    $this->getJson('/api/v1/spatial/zones/'.$draft->public_id)->assertStatus(404);
    $this->getJson('/api/v1/spatial/zones?bbox=43,40,46,43')
        ->assertOk()
        ->assertJsonMissing(['id' => $draft->public_id]);

    expect(SpatialDatasetVersion::query()->where('review_status', SpatialReviewStatus::Published)->count())->toBe(1);
});

it('returns conflict for overlapping contradictory assignments and combines a closed season', function (): void {
    $admin = $this->createAdmin();
    $legal = LegalFixtures::approvedCitationStack($admin);
    $prohibit = SpatialFixtures::publishedHuntingRule($legal['provision'], $admin, LegalRuleEffect::Prohibit);
    $allow = SpatialFixtures::publishedHuntingRule($legal['provision'], $admin, LegalRuleEffect::Allow);
    $published = SpatialFixtures::publishedZone($admin);
    SpatialFixtures::assignProhibition($published['zone'], $prohibit, $admin, SpatialAssignmentType::ProhibitedWithin);
    SpatialFixtures::assignProhibition($published['zone'], $allow, $admin, SpatialAssignmentType::AppliesWithin);

    $this->getJson('/api/v1/spatial/evaluate?lng=44.5&lat=41.5&activity=hunting&jurisdiction=XX')
        ->assertOk()
        ->assertJsonPath('data.outcome', LegalConclusion::Conflict->value);

    $this->actingAs($admin, 'web')->getJson('/api/v1/admin/spatial/conflicts')
        ->assertOk()
        ->assertJsonPath('data.0.evidence', 'Overlapping spatial assignments without reviewed precedence.');

    $species = Species::factory()->published()->create(['scientific_name' => 'Testus spatialis']);
    LegalFixtures::publishedSeason($legal['provision'], $admin, $species->id, LegalActivityType::Hunting, [
        'start_month' => 1,
        'start_day' => 1,
        'end_month' => 1,
        'end_day' => 2,
        'first_season_year' => 2024,
        'last_season_year' => 2027,
    ]);

    $closed = $this->getJson('/api/v1/spatial/evaluate?lng=44.5&lat=41.5&activity=hunting&jurisdiction=XX&from=2026-06-01&to=2026-06-07&species='.$species->id);
    $closed->assertOk();
    expect($closed->json('data.outcome'))->not->toBe(LegalConclusion::Allowed->value);
});

it('uses php point-in-polygon for holes on every driver and mysql geometry when available', function (): void {
    $admin = $this->createAdmin();
    $donut = MultipolygonGeometry::fromGeoJson([
        'type' => 'Polygon',
        'coordinates' => [
            [[0, 0], [4, 0], [4, 4], [0, 4], [0, 0]],
            [[1, 1], [3, 1], [3, 3], [1, 3], [1, 1]],
        ],
    ]);
    $published = SpatialFixtures::publishedZone($admin, $donut, 'XX');
    $rule = SpatialFixtures::publishedHuntingRule(LegalFixtures::approvedCitationStack($admin)['provision'], $admin);
    SpatialFixtures::assignProhibition($published['zone'], $rule, $admin);

    $this->getJson('/api/v1/spatial/evaluate?lng=2&lat=2&activity=hunting&jurisdiction=XX')
        ->assertOk()
        ->assertJsonPath('data.outcome', LegalConclusion::Unknown->value);
    $this->getJson('/api/v1/spatial/evaluate?lng=0.5&lat=0.5&activity=hunting&jurisdiction=XX')
        ->assertOk()
        ->assertJsonPath('data.outcome', LegalConclusion::Prohibited->value);
});

it('rejects world-sized high-detail viewports', function (): void {
    $this->getJson('/api/v1/spatial/zones?bbox=-180,-90,180,90&detail=full')->assertStatus(422);
});

it('returns public viewport properties, display state, and a stable etag', function (): void {
    $admin = $this->createAdmin();
    $published = SpatialFixtures::publishedZone($admin);
    $rule = SpatialFixtures::publishedHuntingRule(LegalFixtures::approvedCitationStack($admin)['provision'], $admin);
    SpatialFixtures::assignProhibition($published['zone'], $rule, $admin);

    $viewport = '/api/v1/spatial/zones?bbox=43,40,46,43&activity=hunting&detail=region&at=2026-06-01T12:00:00%2B04:00';
    $first = $this->getJson($viewport)
        ->assertOk()
        ->assertJsonPath('data.features.0.properties.public_id', $published['zone']->public_id)
        ->assertJsonPath('data.features.0.properties.legal_state', 'prohibited')
        ->assertJsonPath('data.features.0.id', $published['zone']->public_id);
    expect($first->json('data.features.0.properties'))->not->toHaveKey('storage_path');
    $etag = $first->headers->get('ETag');
    expect($etag)->not->toBeEmpty();

    $this->withHeaders(['If-None-Match' => $etag])
        ->getJson($viewport)
        ->assertStatus(304);

    $this->getJson('/api/v1/spatial/zones?bbox=43,40,46,43&types=not_a_zone')
        ->assertOk()
        ->assertJsonPath('data.features', []);
});

it('searches published zones only and keeps coordinate checks private', function (): void {
    $admin = $this->createAdmin();
    $published = SpatialFixtures::publishedZone($admin);
    SpatialZone::factory()->create([
        'spatial_dataset_id' => $published['dataset']->id,
        'status' => 'draft',
        'is_fictional' => true,
        'default_name' => 'FICTIONAL Hidden Draft',
    ]);

    $found = $this->getJson('/api/v1/spatial/search?q=FICTIONAL')->assertOk();
    $names = collect($found->json('data.results'))->pluck('name');
    expect($names->contains($published['zone']->default_name))->toBeTrue();
    expect($names->contains('FICTIONAL Hidden Draft'))->toBeFalse();

    $evaluated = $this->getJson('/api/v1/spatial/evaluate?lng=44.5&lat=41.5&activity=hunting&jurisdiction=XX')
        ->assertOk();
    expect($evaluated->headers->get('Cache-Control'))->toContain('no-store');
});

it('resolves a published species slug and rejects an oversized period', function (): void {
    $admin = $this->createAdmin();
    $legal = LegalFixtures::approvedCitationStack($admin);
    $published = SpatialFixtures::publishedZone($admin);
    $rule = SpatialFixtures::publishedHuntingRule($legal['provision'], $admin, LegalRuleEffect::Prohibit);
    SpatialFixtures::assignProhibition($published['zone'], $rule, $admin);
    $species = Species::factory()->published()->create(['scientific_name' => 'Testus mapus']);
    LegalFixtures::publishedSeason($legal['provision'], $admin, $species->id, LegalActivityType::Hunting, [
        'start_month' => 1,
        'start_day' => 1,
        'end_month' => 1,
        'end_day' => 2,
        'first_season_year' => 2024,
        'last_season_year' => 2027,
    ]);

    $closed = $this->getJson('/api/v1/spatial/evaluate?lng=44.5&lat=41.5&activity=hunting&jurisdiction=XX&from=2026-06-01&to=2026-06-07&species_slug='.$species->canonical_slug.'&availability=timeline');
    $closed->assertOk()
        ->assertJsonPath('data.outcome', LegalConclusion::Prohibited->value);
    expect($closed->json('data.seasonal_availability.results.0.overall_state'))->not->toBeNull();

    $this->getJson('/api/v1/spatial/evaluate?lng=44.5&lat=41.5&activity=hunting&species_slug=missing-species')
        ->assertStatus(422);
    $this->getJson('/api/v1/spatial/evaluate?lng=44.5&lat=41.5&activity=hunting&from=2020-01-01&to=2026-01-01')
        ->assertStatus(422);
});

it('rate limits public spatial browsing', function (): void {
    $this->flushApplicationCacheSafely();
    config(['spatial.rate_limits.public_per_minute' => 2]);
    $this->getJson('/api/v1/spatial/zones?bbox=43,40,46,43')->assertOk();
    $this->getJson('/api/v1/spatial/zones?bbox=43,40,46,43')->assertOk();
    $this->getJson('/api/v1/spatial/zones?bbox=43,40,46,43')->assertStatus(429);
});
