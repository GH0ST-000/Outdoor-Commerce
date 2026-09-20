<?php

declare(strict_types=1);

use App\Domains\Catalog\Models\MediaAttachment;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Identity\Enums\Role;
use App\Domains\Identity\Models\User;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Operations\Models\AuditLog;
use Tests\Support\InteractsWithAccessControl;
use Tests\Support\MediaFixtures;

uses(InteractsWithAccessControl::class);

beforeEach(function (): void {
    MediaFixtures::fakeDisks();
});

it('lists a product gallery with responsive sources for ready assets only', function (): void {
    $manager = $this->createUserWithRole(Role::CatalogManager);
    $product = Product::factory()->create();

    MediaAttachment::factory()->forProduct($product)->ready()->primary()->withKaEnAlt()->create();
    MediaAttachment::factory()->forProduct($product)->pending()->sortOrder(1)->create();

    $response = $this->actingAs($manager, 'web')
        ->getJson("/api/v1/admin/products/{$product->id}/media")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.status', 'ready')
        ->assertJsonPath('data.0.is_primary', true)
        ->assertJsonPath('data.0.alt_text', 'ოპტიკური სამიზნე')
        ->assertJsonPath('data.1.status', 'pending')
        ->assertJsonPath('data.1.sources', null);

    expect($response->json('data.0.sources.webp.presets.card.url'))
        ->toStartWith('http://localhost/storage/media/derivatives/')
        ->and($response->json('data.0.sources.webp.srcset'))->toContain('240w');

    // Locale override falls through to the requested language.
    $this->actingAs($manager, 'web')
        ->getJson("/api/v1/admin/products/{$product->id}/media?locale=en")
        ->assertJsonPath('data.0.alt_text', 'Rifle scope');
});

it('falls back to georgian alt text when the requested locale has none', function (): void {
    $manager = $this->createUserWithRole(Role::CatalogManager);
    $product = Product::factory()->create();

    MediaAttachment::factory()->forProduct($product)->ready()->withKaAlt('ქართული ტექსტი')->create();

    $this->actingAs($manager, 'web')
        ->getJson("/api/v1/admin/products/{$product->id}/media?locale=en")
        ->assertOk()
        ->assertJsonPath('data.0.alt_text', 'ქართული ტექსტი');
});

it('updates alt text, caption, and focal point', function (): void {
    $manager = $this->createUserWithRole(Role::CatalogManager);
    $product = Product::factory()->create();
    $attachment = MediaAttachment::factory()->forProduct($product)->ready()->create();

    $this->actingAs($manager, 'web')
        ->patchJson("/api/v1/admin/products/{$product->id}/media/{$attachment->id}", [
            'translations' => [
                ['locale' => 'ka', 'alt_text' => 'ოპტიკა', 'caption' => 'ქართული წარწერა'],
                ['locale' => 'en', 'alt_text' => 'Optics'],
            ],
            'focal_point' => ['x' => 0.25, 'y' => 0.75],
        ])
        ->assertOk()
        ->assertJsonPath('data.alt_text', 'ოპტიკა')
        ->assertJsonPath('data.caption', 'ქართული წარწერა')
        ->assertJsonPath('data.focal_point.x', 0.25)
        ->assertJsonPath('data.focal_point.y', 0.75)
        ->assertJsonPath('data.translations.en.alt_text', 'Optics');

    expect(AuditLog::query()->where('event', AuditEvent::MediaMetadataUpdated->value)->exists())->toBeTrue();
});

it('validates focal point coordinates and locales', function (): void {
    $manager = $this->createUserWithRole(Role::CatalogManager);
    $product = Product::factory()->create();
    $attachment = MediaAttachment::factory()->forProduct($product)->ready()->create();
    $url = "/api/v1/admin/products/{$product->id}/media/{$attachment->id}";

    $this->actingAs($manager, 'web')
        ->patchJson($url, ['focal_point' => ['x' => 1.5, 'y' => 0.5]])
        ->assertUnprocessable();

    $this->actingAs($manager, 'web')
        ->patchJson($url, ['focal_point' => ['x' => 0.5]])
        ->assertUnprocessable();

    $this->actingAs($manager, 'web')
        ->patchJson($url, ['translations' => [['locale' => 'de', 'alt_text' => 'Zielfernrohr']]])
        ->assertUnprocessable();

    expect($attachment->refresh()->focal_point_x)->toBeNull();
});

it('reorders a gallery only when the full ordering is submitted', function (): void {
    $manager = $this->createUserWithRole(Role::CatalogManager);
    $product = Product::factory()->create();

    $first = MediaAttachment::factory()->forProduct($product)->ready()->sortOrder(0)->create();
    $second = MediaAttachment::factory()->forProduct($product)->ready()->sortOrder(1)->create();
    $third = MediaAttachment::factory()->forProduct($product)->ready()->sortOrder(2)->create();
    $url = "/api/v1/admin/products/{$product->id}/media/reorder";

    // Partial lists are refused rather than merged.
    $this->actingAs($manager, 'web')
        ->postJson($url, ['attachment_ids' => [$third->id, $first->id]])
        ->assertUnprocessable()
        ->assertJsonPath('error.details.attachment_ids.0', 'The ordering must list every attachment for this owner exactly once.');

    $this->actingAs($manager, 'web')
        ->postJson($url, ['attachment_ids' => [$first->id, $first->id, $second->id]])
        ->assertUnprocessable();

    $this->actingAs($manager, 'web')
        ->postJson($url, ['attachment_ids' => [$third->id, $second->id, $first->id]])
        ->assertOk()
        ->assertJsonPath('data.0.id', $third->id)
        ->assertJsonPath('data.2.id', $first->id);

    expect($third->refresh()->sort_order)->toBe(0)
        ->and($second->refresh()->sort_order)->toBe(1)
        ->and($first->refresh()->sort_order)->toBe(2);

    // Reordering re-elects the primary from the new first position.
    expect($third->refresh()->is_primary)->toBeTrue()
        ->and($first->refresh()->is_primary)->toBeFalse();

    expect(AuditLog::query()->where('event', AuditEvent::MediaReordered->value)->exists())->toBeTrue();
});

it('keeps exactly one primary and refuses to promote an unprocessed image', function (): void {
    $manager = $this->createUserWithRole(Role::CatalogManager);
    $product = Product::factory()->create();

    $ready = MediaAttachment::factory()->forProduct($product)->ready()->primary()->create();
    $other = MediaAttachment::factory()->forProduct($product)->ready()->sortOrder(1)->create();
    $pending = MediaAttachment::factory()->forProduct($product)->pending()->sortOrder(2)->create();

    $this->actingAs($manager, 'web')
        ->patchJson("/api/v1/admin/products/{$product->id}/media/{$other->id}/primary")
        ->assertOk()
        ->assertJsonPath('data.is_primary', true);

    expect($product->mediaAttachments()->where('is_primary', true)->count())->toBe(1)
        ->and($ready->refresh()->is_primary)->toBeFalse()
        ->and($other->refresh()->is_primary)->toBeTrue();

    $this->actingAs($manager, 'web')
        ->patchJson("/api/v1/admin/products/{$product->id}/media/{$pending->id}/primary")
        ->assertUnprocessable()
        ->assertJsonPath('error.details.attachment.0', 'Only a processed image can be the primary image.');

    expect($other->refresh()->is_primary)->toBeTrue();
    expect(AuditLog::query()->where('event', AuditEvent::MediaPrimaryChanged->value)->exists())->toBeTrue();
});

it('re-elects the primary by sort order when one is removed', function (): void {
    $manager = $this->createUserWithRole(Role::CatalogManager);
    $product = Product::factory()->create();

    $primary = MediaAttachment::factory()->forProduct($product)->ready()->primary()->sortOrder(0)->create();
    $middle = MediaAttachment::factory()->forProduct($product)->ready()->sortOrder(1)->create();
    $last = MediaAttachment::factory()->forProduct($product)->ready()->sortOrder(2)->create();

    $this->actingAs($manager, 'web')
        ->deleteJson("/api/v1/admin/products/{$product->id}/media/{$primary->id}")
        ->assertOk();

    expect($primary->refresh()->trashed())->toBeTrue()
        ->and($middle->refresh()->is_primary)->toBeTrue()
        ->and($last->refresh()->is_primary)->toBeFalse();

    // The asset and its files survive the detach; cleanup decides their fate later.
    expect($primary->asset()->withTrashed()->exists())->toBeTrue();

    expect(AuditLog::query()->where('event', AuditEvent::MediaAttachmentRemoved->value)->exists())->toBeTrue();
});

it('leaves no primary when the only remaining images are unprocessed', function (): void {
    $manager = $this->createUserWithRole(Role::CatalogManager);
    $product = Product::factory()->create();

    $primary = MediaAttachment::factory()->forProduct($product)->ready()->primary()->create();
    $pending = MediaAttachment::factory()->forProduct($product)->pending()->sortOrder(1)->create();

    $this->actingAs($manager, 'web')
        ->deleteJson("/api/v1/admin/products/{$product->id}/media/{$primary->id}")
        ->assertOk();

    expect($pending->refresh()->is_primary)->toBeFalse()
        ->and($product->mediaAttachments()->where('is_primary', true)->count())->toBe(0);
});

it('answers 404 when an attachment does not belong to the addressed owner', function (): void {
    $manager = $this->createUserWithRole(Role::CatalogManager);
    $product = Product::factory()->create();
    $otherProduct = Product::factory()->create();
    $variant = ProductVariant::factory()->forProduct($product)->create();
    $otherVariant = ProductVariant::factory()->forProduct($otherProduct)->create();

    $productAttachment = MediaAttachment::factory()->forProduct($product)->ready()->create();
    $variantAttachment = MediaAttachment::factory()->forVariant($variant)->ready()->create();

    // Right attachment, wrong product.
    $this->actingAs($manager, 'web')
        ->patchJson("/api/v1/admin/products/{$otherProduct->id}/media/{$productAttachment->id}")
        ->assertNotFound();

    // A variant attachment is not addressable through the product gallery.
    $this->actingAs($manager, 'web')
        ->patchJson("/api/v1/admin/products/{$product->id}/media/{$variantAttachment->id}")
        ->assertNotFound();

    // Variant belongs to another product.
    $this->actingAs($manager, 'web')
        ->getJson("/api/v1/admin/products/{$product->id}/variants/{$otherVariant->id}/media")
        ->assertNotFound();

    // Attachment belongs to another variant.
    $this->actingAs($manager, 'web')
        ->patchJson("/api/v1/admin/products/{$product->id}/variants/{$variant->id}/media/{$productAttachment->id}")
        ->assertNotFound();
});

it('scopes variant galleries to the variant', function (): void {
    $manager = $this->createUserWithRole(Role::CatalogManager);
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->forProduct($product)->create();

    MediaAttachment::factory()->forProduct($product)->ready()->create();
    $variantAttachment = MediaAttachment::factory()->forVariant($variant)->ready()->create();

    $this->actingAs($manager, 'web')
        ->getJson("/api/v1/admin/products/{$product->id}/variants/{$variant->id}/media")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $variantAttachment->id);

    expect($variantAttachment->mediable_type)->toBe('product_variant');
});

it('requires catalog.manage for mutations and catalog.view for reads', function (): void {
    $product = Product::factory()->create();
    $attachment = MediaAttachment::factory()->forProduct($product)->ready()->create();
    $customer = User::factory()->create();

    $this->getJson("/api/v1/admin/products/{$product->id}/media")->assertUnauthorized();

    $this->actingAs($customer, 'web')
        ->getJson("/api/v1/admin/products/{$product->id}/media")
        ->assertForbidden();

    $this->actingAs($customer, 'web')
        ->deleteJson("/api/v1/admin/products/{$product->id}/media/{$attachment->id}")
        ->assertForbidden();

    $orderManager = $this->createUserWithRole(Role::OrderManager);

    $this->actingAs($orderManager, 'web')
        ->deleteJson("/api/v1/admin/products/{$product->id}/media/{$attachment->id}")
        ->assertForbidden();

    expect($attachment->refresh()->trashed())->toBeFalse();
});
