<?php

declare(strict_types=1);

use App\Domains\Catalog\Models\MediaAttachment;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Services\Media\MediaPresentationService;
use Tests\Support\MediaFixtures;

beforeEach(function (): void {
    MediaFixtures::fakeDisks();
});

afterEach(function (): void {
    MediaFixtures::cleanup();
});

it('returns variant media when the variant has ready attachments', function (): void {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->forProduct($product)->create();

    MediaAttachment::factory()->forProduct($product)->ready()->primary()->create();
    $variantAttachment = MediaAttachment::factory()->forVariant($variant)->ready()->primary()->create();

    $gallery = app(MediaPresentationService::class)->displayGalleryForVariant($variant);

    expect($gallery)->toHaveCount(1)
        ->and($gallery[0]['id'])->toBe($variantAttachment->id)
        ->and($gallery[0]['status'])->toBe('ready');
});

it('falls back to product media when the variant has no ready attachments', function (): void {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->forProduct($product)->create();

    $productAttachment = MediaAttachment::factory()->forProduct($product)->ready()->primary()->create();
    MediaAttachment::factory()->forVariant($variant)->pending()->create();

    $gallery = app(MediaPresentationService::class)->displayGalleryForVariant($variant->fresh());

    expect($gallery)->toHaveCount(1)
        ->and($gallery[0]['id'])->toBe($productAttachment->id);
});

it('returns an empty gallery when neither variant nor product has ready media', function (): void {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->forProduct($product)->create();

    MediaAttachment::factory()->forProduct($product)->pending()->create();

    expect(app(MediaPresentationService::class)->displayGalleryForVariant($variant))->toBe([]);
});
