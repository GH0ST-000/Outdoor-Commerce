<?php

declare(strict_types=1);

use App\Domains\Geography\Enums\SpatialReviewStatus;
use App\Domains\Geography\Enums\SpatialZoneStatus;
use App\Domains\Geography\Models\LegalRuleSpatialZone;
use App\Domains\Geography\Models\SpatialDatasetVersion;
use App\Domains\Geography\Models\SpatialZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithAccessControl;
use Tests\Support\SpatialFixtures;

uses(InteractsWithAccessControl::class, RefreshDatabase::class);

beforeEach(function (): void {
    SpatialFixtures::fakeDisk();
});

it('refuses a missing official geometry file without inventing polygons', function (): void {
    $this->artisan('spatial:import', [
        '--source' => storage_path('framework/missing-apa17.geojson'),
        '--dataset' => 'protected-areas-georgia',
        '--version-label' => '2026-04-22',
        '--source-code' => 'APA17',
        '--dry-run' => true,
    ])->assertFailed()
        ->expectsOutputToContain('portal.mepa.gov.ge');

    expect(SpatialZone::query()->count())->toBe(0);
});

it('imports a geojson file as a draft and keeps it off the public map', function (): void {
    $admin = $this->createAdmin();
    $path = storage_path('framework/testing-command-zone.geojson');
    file_put_contents($path, SpatialFixtures::squareGeoJson());

    $this->artisan('spatial:import', [
        '--source' => $path,
        '--dataset' => 'command-test-zones',
        '--version-label' => 'test-1',
        '--source-code' => 'TESTGEO',
        '--identifier-property' => 'code',
        '--name-property' => 'title',
        '--actor' => $admin->id,
    ])->assertSuccessful();

    $zone = SpatialZone::query()->where('external_identifier', 'TEST-ZONE-1')->firstOrFail();
    expect($zone->status)->toBe(SpatialZoneStatus::Draft)
        ->and($zone->dataset->is_fictional)->toBeTrue()
        ->and(SpatialDatasetVersion::query()->where('version_label', 'test-1')->value('review_status'))->toBe(SpatialReviewStatus::Draft)
        ->and(LegalRuleSpatialZone::query()->count())->toBe(0);

    $this->artisan('spatial:import', [
        '--source' => $path,
        '--dataset' => 'command-test-zones',
        '--version-label' => 'test-1',
        '--source-code' => 'TESTGEO',
        '--identifier-property' => 'code',
        '--name-property' => 'title',
        '--actor' => $admin->id,
    ])->assertSuccessful()->expectsOutputToContain('Skipped');

    expect(SpatialZone::query()->count())->toBe(1);

    $this->getJson('/api/v1/spatial/zones?bbox=43,40,46,43')
        ->assertOk()
        ->assertJsonPath('data.features', []);

    @unlink($path);
});

it('rejects geojson features that have no stable identifier property', function (): void {
    $admin = $this->createAdmin();
    $path = storage_path('framework/testing-unstable-zone.geojson');
    file_put_contents($path, SpatialFixtures::squareGeoJson());

    $this->artisan('spatial:import', [
        '--source' => $path,
        '--dataset' => 'command-test-zones',
        '--version-label' => 'test-1',
        '--source-code' => 'TESTGEO',
        '--identifier-property' => 'missing_code',
        '--name-property' => 'title',
        '--actor' => $admin->id,
    ])->assertFailed()->expectsOutputToContain('stable');

    expect(SpatialZone::query()->count())->toBe(0);
    @unlink($path);
});
