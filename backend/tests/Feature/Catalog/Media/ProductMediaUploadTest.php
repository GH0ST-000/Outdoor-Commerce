<?php

declare(strict_types=1);

use App\Domains\Catalog\Enums\MediaStatus;
use App\Domains\Catalog\Models\MediaAsset;
use App\Domains\Catalog\Models\MediaAttachment;
use App\Domains\Catalog\Models\Product;
use App\Domains\Identity\Enums\Role;
use App\Domains\Identity\Models\User;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Operations\Models\AuditLog;
use App\Jobs\ProcessMediaAsset;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\InteractsWithAccessControl;
use Tests\Support\MediaFixtures;

uses(InteractsWithAccessControl::class);

beforeEach(function (): void {
    MediaFixtures::fakeDisks();
});

afterEach(function (): void {
    MediaFixtures::cleanup();
});

function mediaUrl(Product $product): string
{
    return "/api/v1/admin/products/{$product->id}/media";
}

function uploadMedia(User $actor, Product $product, array $files): TestResponse
{
    return test()->actingAs($actor, 'web')->post(
        mediaUrl($product),
        ['files' => $files],
        ['Accept' => 'application/json'],
    );
}

/**
 * Validation failures are rendered through ApiErrorResponse, so the per-file keys
 * live under `error.details` rather than Laravel's default `errors` envelope.
 */
function assertMediaRejected(TestResponse $response, array $keys, array $absentKeys = []): void
{
    $response->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED');

    $details = array_keys((array) $response->json('error.details'));

    expect($details)->toContain(...$keys);

    foreach ($absentKeys as $absent) {
        expect($details)->not->toContain($absent);
    }
}

it('rejects unauthenticated and unauthorized uploads', function (): void {
    $product = Product::factory()->create();

    $this->post(mediaUrl($product), ['files' => [MediaFixtures::jpegUpload()]], ['Accept' => 'application/json'])
        ->assertUnauthorized();

    $customer = User::factory()->create();
    uploadMedia($customer, $product, [MediaFixtures::jpegUpload()])->assertForbidden();

    expect(MediaAsset::query()->count())->toBe(0);
});

it('accepts jpeg, png, and webp uploads and answers 202', function (): void {
    Queue::fake();

    $manager = $this->createUserWithRole(Role::CatalogManager);
    $product = Product::factory()->create();

    $response = uploadMedia($manager, $product, [
        MediaFixtures::jpegUpload('Rifle Scope 4x32.JPG'),
        MediaFixtures::pngUpload(),
        MediaFixtures::webpUpload(),
    ]);

    $response->assertStatus(202)
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.status', MediaStatus::Pending->value)
        ->assertJsonPath('data.0.sources', null);

    expect(MediaAsset::query()->count())->toBe(3);
    expect($product->mediaAttachments()->count())->toBe(3);
    expect(MediaAsset::query()->pluck('mime_type')->sort()->values()->all())
        ->toBe(['image/jpeg', 'image/png', 'image/webp']);

    // Sort order is dense and starts at zero.
    expect($product->mediaAttachments()->pluck('sort_order')->all())->toBe([0, 1, 2]);

    // The stored original is named from a server-generated UUID, not the upload.
    $asset = MediaAsset::query()->firstOrFail();
    expect($asset->original_path)->toContain($asset->uuid)
        ->and($asset->original_path)->not->toContain('Rifle')
        ->and($asset->original_filename)->toBe('Rifle Scope 4x32');
    Storage::disk('media_private')->assertExists($asset->original_path);

    Queue::assertPushed(ProcessMediaAsset::class, 3);
    expect(AuditLog::query()->where('event', AuditEvent::MediaUploaded->value)->count())->toBe(3);
});

it('never exposes the private disk or path of an original', function (): void {
    Queue::fake();

    $manager = $this->createUserWithRole(Role::CatalogManager);
    $product = Product::factory()->create();

    $response = uploadMedia($manager, $product, [MediaFixtures::jpegUpload()]);
    $asset = MediaAsset::query()->firstOrFail();

    $response->assertStatus(202)
        ->assertJsonMissingPath('data.0.original_disk')
        ->assertJsonMissingPath('data.0.original_path');

    expect($response->getContent())
        ->not->toContain('media_private')
        ->and($response->getContent())->not->toContain($asset->original_path)
        ->and($response->getContent())->not->toContain('originals/');
});

it('rejects an svg disguised as an image', function (): void {
    $manager = $this->createUserWithRole(Role::CatalogManager);
    $product = Product::factory()->create();

    assertMediaRejected(
        uploadMedia($manager, $product, [MediaFixtures::upload(MediaFixtures::svg(), 'logo.svg')]),
        ['files.0'],
    );

    // Also refused when the extension lies about the content.
    assertMediaRejected(
        uploadMedia($manager, $product, [MediaFixtures::upload(MediaFixtures::svg(), 'logo.png')]),
        ['files.0'],
    );

    expect(MediaAsset::query()->count())->toBe(0);
});

it('rejects executables, polyglots, and empty files', function (): void {
    $manager = $this->createUserWithRole(Role::CatalogManager);
    $product = Product::factory()->create();

    $cases = [
        'payload.jpg' => MediaFixtures::executable(),
        'shell.jpg' => MediaFixtures::polyglot(),
        'empty.jpg' => '',
    ];

    foreach ($cases as $name => $contents) {
        assertMediaRejected(
            uploadMedia($manager, $product, [MediaFixtures::upload($contents, $name)]),
            ['files.0'],
        );
    }

    expect(MediaAsset::query()->count())->toBe(0);
});

it('rejects oversized files, undersized images, and megapixel bombs', function (): void {
    $manager = $this->createUserWithRole(Role::CatalogManager);
    $product = Product::factory()->create();

    config(['media.uploads.max_file_size_kilobytes' => 1]);
    uploadMedia($manager, $product, [MediaFixtures::jpegUpload('big.jpg', 1200, 900)])
        ->assertUnprocessable();

    config(['media.uploads.max_file_size_kilobytes' => 12288]);
    assertMediaRejected(
        uploadMedia($manager, $product, [MediaFixtures::jpegUpload('tiny.jpg', 64, 64)]),
        ['files.0'],
    );

    config(['media.dimensions.max_pixels' => 1000]);
    assertMediaRejected(
        uploadMedia($manager, $product, [MediaFixtures::jpegUpload('bomb.jpg', 800, 600)]),
        ['files.0'],
    );

    expect(MediaAsset::query()->count())->toBe(0);
});

it('rejects the whole batch when a single file is invalid', function (): void {
    $manager = $this->createUserWithRole(Role::CatalogManager);
    $product = Product::factory()->create();
    Queue::fake();

    assertMediaRejected(
        uploadMedia($manager, $product, [
            MediaFixtures::jpegUpload('good.jpg'),
            MediaFixtures::upload(MediaFixtures::svg(), 'bad.svg'),
            MediaFixtures::pngUpload('also-good.png'),
        ]),
        ['files.1'],
        ['files.0', 'files.2'],
    );

    // Atomic: no partial gallery, and nothing left behind on the private disk.
    expect(MediaAsset::query()->count())->toBe(0)
        ->and(MediaAttachment::query()->count())->toBe(0)
        ->and(Storage::disk('media_private')->allFiles())->toBe([]);

    Queue::assertNothingPushed();
});

it('enforces the per-owner gallery limit', function (): void {
    Queue::fake();

    $manager = $this->createUserWithRole(Role::CatalogManager);
    $product = Product::factory()->create();

    config(['media.uploads.max_product_gallery' => 2]);

    uploadMedia($manager, $product, [MediaFixtures::jpegUpload('one.jpg')])->assertStatus(202);
    uploadMedia($manager, $product, [MediaFixtures::jpegUpload('two.jpg')])->assertStatus(202);

    assertMediaRejected(
        uploadMedia($manager, $product, [MediaFixtures::jpegUpload('three.jpg')]),
        ['files'],
    );

    expect($product->mediaAttachments()->count())->toBe(2);
});

it('rejects more files than the per-request limit allows', function (): void {
    $manager = $this->createUserWithRole(Role::CatalogManager);
    $product = Product::factory()->create();

    config(['media.uploads.max_files_per_request' => 2]);

    assertMediaRejected(
        uploadMedia($manager, $product, [
            MediaFixtures::jpegUpload('one.jpg'),
            MediaFixtures::jpegUpload('two.jpg'),
            MediaFixtures::jpegUpload('three.jpg'),
        ]),
        ['files'],
    );

    expect(MediaAsset::query()->count())->toBe(0);
});
