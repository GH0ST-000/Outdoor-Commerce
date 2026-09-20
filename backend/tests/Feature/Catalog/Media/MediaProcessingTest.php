<?php

declare(strict_types=1);

use App\Domains\Catalog\Enums\MediaFormat;
use App\Domains\Catalog\Enums\MediaPreset;
use App\Domains\Catalog\Enums\MediaStatus;
use App\Domains\Catalog\Models\MediaAsset;
use App\Domains\Catalog\Models\MediaAttachment;
use App\Domains\Catalog\Models\MediaDerivative;
use App\Domains\Catalog\Models\Product;
use App\Domains\Identity\Enums\Role;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Operations\Models\AuditLog;
use App\Jobs\ProcessMediaAsset;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\InteractsWithAccessControl;
use Tests\Support\MediaFixtures;

uses(InteractsWithAccessControl::class);

beforeEach(function (): void {
    MediaFixtures::fakeDisks();
});

afterEach(function (): void {
    MediaFixtures::cleanup();
});

/**
 * Stores an original the way UploadMediaAction would, without going through HTTP.
 */
function seedPendingAsset(Product $product, ?string $contents = null, string $extension = 'jpg'): MediaAttachment
{
    $contents ??= MediaFixtures::jpeg(1000, 750);
    $uuid = (string) Str::uuid();
    $createdAt = now();
    $path = sprintf('originals/%s/%s/%s/original.%s', $createdAt->format('Y'), $createdAt->format('m'), $uuid, $extension);

    Storage::disk('media_private')->put($path, $contents);

    $asset = MediaAsset::factory()->pending()->create([
        'uuid' => $uuid,
        'original_path' => $path,
        'original_extension' => $extension,
        'mime_type' => $extension === 'png' ? 'image/png' : 'image/jpeg',
        'byte_size' => strlen($contents),
        'width' => null,
        'height' => null,
        'created_at' => $createdAt,
    ]);

    return MediaAttachment::factory()->forProduct($product)->forAsset($asset)->create();
}

it('processes a pending asset into derivatives across formats', function (): void {
    $product = Product::factory()->create();
    $attachment = seedPendingAsset($product);

    ProcessMediaAsset::dispatchSync($attachment->media_asset_id);

    $asset = $attachment->asset->refresh();

    expect($asset->status)->toBe(MediaStatus::Ready)
        ->and($asset->width)->toBe(1000)
        ->and($asset->height)->toBe(750)
        ->and($asset->processed_at)->not->toBeNull()
        ->and($asset->failure_code)->toBeNull();

    // Every preset exists in WebP plus a raster fallback.
    foreach (MediaPreset::ordered() as $preset) {
        $webp = $asset->derivative($preset, MediaFormat::Webp);
        expect($webp)->not->toBeNull();
        Storage::disk('media_public')->assertExists($webp->path);
    }

    expect($asset->derivatives()->where('format', MediaFormat::Jpeg->value)->count())
        ->toBe(count(MediaPreset::ordered()));

    expect(AuditLog::query()->where('event', AuditEvent::MediaProcessingCompleted->value)->exists())->toBeTrue();
});

it('preserves aspect ratio and never upscales', function (): void {
    $product = Product::factory()->create();
    // Smaller than every preset except thumbnail, and not square.
    $attachment = seedPendingAsset($product, MediaFixtures::jpeg(400, 300));

    ProcessMediaAsset::dispatchSync($attachment->media_asset_id);
    $asset = $attachment->asset->refresh();

    $zoom = $asset->derivative(MediaPreset::Zoom, MediaFormat::Webp);
    $thumbnail = $asset->derivative(MediaPreset::Thumbnail, MediaFormat::Webp);

    expect($zoom->width)->toBe(400)
        ->and($zoom->height)->toBe(300)
        ->and($thumbnail->width)->toBe(240)
        ->and($thumbnail->height)->toBe(180);
});

it('is idempotent when the same asset is processed twice', function (): void {
    $product = Product::factory()->create();
    $attachment = seedPendingAsset($product);

    ProcessMediaAsset::dispatchSync($attachment->media_asset_id);
    $asset = $attachment->asset->refresh();
    $firstCount = $asset->derivatives()->count();
    $firstIds = $asset->derivatives()->pluck('id')->all();

    // A ready asset short-circuits.
    ProcessMediaAsset::dispatchSync($attachment->media_asset_id);
    expect($asset->refresh()->derivatives()->pluck('id')->all())->toBe($firstIds);

    // A forced reprocess replaces the row set rather than duplicating it.
    $asset->forceFill(['status' => MediaStatus::Pending])->save();
    ProcessMediaAsset::dispatchSync($attachment->media_asset_id);

    expect($asset->refresh()->status)->toBe(MediaStatus::Ready)
        ->and($asset->derivatives()->count())->toBe($firstCount)
        ->and(MediaDerivative::query()->count())->toBe($firstCount);
});

it('promotes the first ready attachment to primary', function (): void {
    $product = Product::factory()->create();
    $first = seedPendingAsset($product);
    $second = seedPendingAsset($product);
    $second->forceFill(['sort_order' => 1])->save();

    expect($first->refresh()->is_primary)->toBeFalse();

    ProcessMediaAsset::dispatchSync($first->media_asset_id);
    ProcessMediaAsset::dispatchSync($second->media_asset_id);

    expect($first->refresh()->is_primary)->toBeTrue()
        ->and($second->refresh()->is_primary)->toBeFalse();
});

it('marks an asset failed when its original is missing', function (): void {
    $product = Product::factory()->create();
    $attachment = seedPendingAsset($product);
    Storage::disk('media_private')->delete($attachment->asset->original_path);

    ProcessMediaAsset::dispatchSync($attachment->media_asset_id);

    $asset = $attachment->asset->refresh();

    expect($asset->status)->toBe(MediaStatus::Failed)
        ->and($asset->failure_code)->toBe('processing_error')
        ->and($asset->failure_message)->toBe('Image processing failed. Please retry.')
        // The safe message must not leak the storage location.
        ->and($asset->failure_message)->not->toContain('originals/');

    expect(AuditLog::query()->where('event', AuditEvent::MediaProcessingFailed->value)->exists())->toBeTrue();
});

it('quarantines an asset whose stored bytes fail the security re-check', function (): void {
    $product = Product::factory()->create();
    $attachment = seedPendingAsset($product);

    // Simulate the file being swapped after upload validation passed.
    Storage::disk('media_private')->put($attachment->asset->original_path, MediaFixtures::svg());

    ProcessMediaAsset::dispatchSync($attachment->media_asset_id);

    $asset = $attachment->asset->refresh();

    expect($asset->status)->toBe(MediaStatus::Quarantined)
        ->and($asset->failure_code)->toBe('content_rejected')
        ->and($asset->derivatives()->count())->toBe(0)
        ->and($attachment->refresh()->is_primary)->toBeFalse();
});

it('exposes status and a retry endpoint for a failed asset', function (): void {
    $manager = $this->createUserWithRole(Role::CatalogManager);
    $product = Product::factory()->create();
    $attachment = seedPendingAsset($product);
    Storage::disk('media_private')->delete($attachment->asset->original_path);

    ProcessMediaAsset::dispatchSync($attachment->media_asset_id);
    $assetId = $attachment->media_asset_id;

    $this->actingAs($manager, 'web')
        ->getJson("/api/v1/admin/media/{$assetId}/status")
        ->assertOk()
        ->assertJsonPath('data.status', MediaStatus::Failed->value)
        ->assertJsonPath('data.failure_code', 'processing_error')
        ->assertJsonMissingPath('data.original_path');

    // Restore the file so the retry can succeed.
    Storage::disk('media_private')->put($attachment->asset->refresh()->original_path, MediaFixtures::jpeg(600, 400));

    $this->actingAs($manager, 'web')
        ->postJson("/api/v1/admin/products/{$product->id}/media/{$attachment->id}/retry")
        ->assertStatus(202);

    expect($attachment->asset->refresh()->status)->toBe(MediaStatus::Ready);
    expect(AuditLog::query()->where('event', AuditEvent::MediaRetryRequested->value)->exists())->toBeTrue();
});

it('refuses to retry a quarantined asset', function (): void {
    $manager = $this->createUserWithRole(Role::CatalogManager);
    $product = Product::factory()->create();
    $attachment = MediaAttachment::factory()->forProduct($product)->quarantined()->create();

    $this->actingAs($manager, 'web')
        ->postJson("/api/v1/admin/products/{$product->id}/media/{$attachment->id}/retry")
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_FAILED');
});
