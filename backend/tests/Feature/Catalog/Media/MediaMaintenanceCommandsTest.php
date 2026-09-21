<?php

declare(strict_types=1);

use App\Domains\Catalog\Enums\MediaStatus;
use App\Domains\Catalog\Models\MediaAsset;
use App\Domains\Catalog\Models\MediaAttachment;
use App\Domains\Catalog\Models\MediaDerivative;
use App\Domains\Catalog\Models\Product;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Operations\Models\AuditLog;
use App\Jobs\ProcessMediaAsset;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MediaFixtures;

beforeEach(function (): void {
    MediaFixtures::fakeDisks();
});

/**
 * Creates an asset with real files on both disks so cleanup has something to delete.
 */
function seedAssetWithFiles(array $attributes = []): MediaAsset
{
    $asset = MediaAsset::factory()->withWebpDerivatives()->create($attributes);

    Storage::disk('media_private')->put($asset->original_path, MediaFixtures::jpeg(400, 300));

    foreach ($asset->derivatives as $derivative) {
        Storage::disk('media_public')->put($derivative->path, MediaFixtures::webp(200, 150));
    }

    return $asset->refresh();
}

it('reports orphans without deleting anything in dry-run mode', function (): void {
    $orphan = seedAssetWithFiles(['created_at' => now()->subDays(3)]);

    $this->artisan('media:cleanup-orphans', ['--dry-run' => true])
        ->expectsOutputToContain('1 orphaned asset(s) would be deleted')
        ->assertSuccessful();

    expect(MediaAsset::query()->whereKey($orphan->id)->exists())->toBeTrue()
        ->and(Storage::disk('media_private')->exists($orphan->original_path))->toBeTrue();

    Storage::disk('media_public')->assertExists($orphan->derivatives->first()->path);
    expect(AuditLog::query()->where('event', AuditEvent::MediaOrphanDeleted->value)->exists())->toBeFalse();
});

it('deletes orphaned assets and their recorded files', function (): void {
    $orphan = seedAssetWithFiles(['created_at' => now()->subDays(3)]);
    $originalPath = $orphan->original_path;
    $derivativePaths = $orphan->derivatives->pluck('path')->all();

    $this->artisan('media:cleanup-orphans')->assertSuccessful();

    expect(MediaAsset::withTrashed()->whereKey($orphan->id)->exists())->toBeFalse()
        ->and(MediaDerivative::query()->where('media_asset_id', $orphan->id)->exists())->toBeFalse()
        ->and(Storage::disk('media_private')->exists($originalPath))->toBeFalse();

    foreach ($derivativePaths as $path) {
        Storage::disk('media_public')->assertMissing($path);
    }

    expect(AuditLog::query()->where('event', AuditEvent::MediaOrphanDeleted->value)->exists())->toBeTrue();
});

it('spares attached assets and anything inside the grace period', function (): void {
    $product = Product::factory()->create();

    $attached = seedAssetWithFiles(['created_at' => now()->subDays(3)]);
    MediaAttachment::factory()->forProduct($product)->forAsset($attached)->create();

    $recent = seedAssetWithFiles(['created_at' => now()->subMinutes(5)]);

    $this->artisan('media:cleanup-orphans')->assertSuccessful();

    expect(MediaAsset::query()->whereKey($attached->id)->exists())->toBeTrue()
        ->and(MediaAsset::query()->whereKey($recent->id)->exists())->toBeTrue()
        ->and(Storage::disk('media_private')->exists($recent->original_path))->toBeTrue();
});

it('gives a detached asset a fresh grace period before collecting it', function (): void {
    $product = Product::factory()->create();
    $asset = seedAssetWithFiles(['created_at' => now()->subDays(30)]);
    $attachment = MediaAttachment::factory()->forProduct($product)->forAsset($asset)->create();

    // A recent removal is recoverable even though the asset itself is old.
    $attachment->delete();
    $this->artisan('media:cleanup-orphans')->assertSuccessful();

    expect(MediaAsset::query()->whereKey($asset->id)->exists())->toBeTrue()
        ->and(Storage::disk('media_private')->exists($asset->original_path))->toBeTrue();

    // Once the removal ages past the window, the bytes go.
    $attachment->forceFill(['deleted_at' => now()->subDays(2)])->saveQuietly();
    $this->artisan('media:cleanup-orphans')->assertSuccessful();

    expect(MediaAsset::withTrashed()->whereKey($asset->id)->exists())->toBeFalse()
        ->and(Storage::disk('media_private')->exists($asset->original_path))->toBeFalse();
});

it('reports stalled assets in dry-run mode without requeueing them', function (): void {
    Queue::fake();

    $stuck = MediaAsset::factory()->stuckProcessing()->create();
    MediaAsset::factory()->processing()->create();

    $this->artisan('media:recover-stuck', ['--dry-run' => true])
        ->expectsOutputToContain('1 stalled asset(s) would be requeued')
        ->assertSuccessful();

    expect($stuck->refresh()->status)->toBe(MediaStatus::Processing);
    Queue::assertNothingPushed();
});

it('requeues stalled pending and processing assets', function (): void {
    Queue::fake();

    $stuckProcessing = MediaAsset::factory()->stuckProcessing()->create();
    $stuckPending = MediaAsset::factory()->pending()->create(['created_at' => now()->subHours(2)]);
    $freshPending = MediaAsset::factory()->pending()->create();
    $ready = MediaAsset::factory()->ready()->create(['processed_at' => now()->subDays(1)]);

    $this->artisan('media:recover-stuck')->assertSuccessful();

    expect($stuckProcessing->refresh()->status)->toBe(MediaStatus::Pending)
        ->and($stuckProcessing->refresh()->processing_started_at)->toBeNull()
        ->and($stuckPending->refresh()->status)->toBe(MediaStatus::Pending)
        ->and($freshPending->refresh()->status)->toBe(MediaStatus::Pending)
        ->and($ready->refresh()->status)->toBe(MediaStatus::Ready);

    Queue::assertPushed(ProcessMediaAsset::class, 2);
});

it('reports nothing to recover when the pipeline is healthy', function (): void {
    MediaAsset::factory()->ready()->create();

    $this->artisan('media:recover-stuck')
        ->expectsOutputToContain('No stalled media assets found.')
        ->assertSuccessful();
});
