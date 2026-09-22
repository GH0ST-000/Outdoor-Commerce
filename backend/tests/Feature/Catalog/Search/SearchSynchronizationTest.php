<?php

declare(strict_types=1);

use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Enums\ProductVariantStatus;
use App\Domains\Catalog\PublicApi\Services\PublicCatalogProjector;
use App\Domains\Catalog\Search\Enums\SearchIndexType;
use App\Domains\Catalog\Search\Services\SearchIndexManager;
use App\Domains\Catalog\Search\Services\SearchSynchronizationService;
use App\Jobs\SyncProductSearchDocuments;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\Support\PublicCatalogFixtures;
use Tests\Support\UsesFakeSearchGateway;

uses(UsesFakeSearchGateway::class);

it('indexes a published product and removes it when unpublished', function (): void {
    $gateway = $this->fakeSearchGateway();
    app(SearchIndexManager::class)->configure();

    $fixture = PublicCatalogFixtures::publicProduct(['ka_name' => 'სამიზნე', 'sku' => 'PRD-ELIG-1']);
    app(SearchSynchronizationService::class)->syncProduct($fixture['product']->id);

    $uid = app(SearchIndexManager::class)->uid(SearchIndexType::Variants, 'ka');
    expect($gateway->documentCount($uid))->toBeGreaterThan(0);

    $fixture['product']->update([
        'status' => ProductStatus::Draft,
        'published_at' => null,
    ]);
    app(PublicCatalogProjector::class)->refreshProductGraph($fixture['product']->id);
    app(SearchSynchronizationService::class)->syncProduct($fixture['product']->id);

    expect($gateway->documentCount($uid))->toBe(0);
});

it('excludes archived variants', function (): void {
    $gateway = $this->fakeSearchGateway();
    app(SearchIndexManager::class)->configure();
    $fixture = PublicCatalogFixtures::publicProduct(['ka_name' => 'გამორთული']);
    $fixture['variant']->update(['status' => ProductVariantStatus::Archived]);
    app(PublicCatalogProjector::class)->refreshProductGraph($fixture['product']->id);
    app(SearchSynchronizationService::class)->syncProduct($fixture['product']->id);

    $uid = app(SearchIndexManager::class)->uid(SearchIndexType::Variants, 'ka');
    expect($gateway->documentCount($uid))->toBe(0);
});

it('does not dispatch an indexing job when the transaction rolls back', function (): void {
    $product = PublicCatalogFixtures::publicProduct()['product'];
    Bus::fake([SyncProductSearchDocuments::class]);

    try {
        DB::transaction(function () use ($product): void {
            $product->update(['sort_order' => 4]);
            throw new RuntimeException('rollback search sync');
        });
    } catch (RuntimeException) {
    }

    Bus::assertNotDispatched(SyncProductSearchDocuments::class);
});

it('dispatches an indexing job after a committed product update', function (): void {
    $product = PublicCatalogFixtures::publicProduct()['product'];
    Bus::fake([SyncProductSearchDocuments::class]);

    DB::transaction(function () use ($product): void {
        $product->update(['sort_order' => 6]);
    });

    Bus::assertDispatched(SyncProductSearchDocuments::class, fn (SyncProductSearchDocuments $job): bool => $job->productId === $product->id);
});

it('retries remain idempotent for the same product', function (): void {
    $gateway = $this->fakeSearchGateway();
    app(SearchIndexManager::class)->configure();
    $fixture = PublicCatalogFixtures::publicProduct(['ka_name' => 'იდემპოტენტური', 'sku' => 'PRD-IDEM-1']);

    $sync = app(SearchSynchronizationService::class);
    $sync->syncProduct($fixture['product']->id);
    $sync->syncProduct($fixture['product']->id);

    $uid = app(SearchIndexManager::class)->uid(SearchIndexType::Variants, 'ka');
    expect($gateway->documentCount($uid))->toBe(1);
});

it('rebuilds swap into the live uid and keeps the previous index', function (): void {
    $gateway = $this->fakeSearchGateway();
    PublicCatalogFixtures::publicProduct(['ka_name' => 'რებილდი']);
    $result = app(SearchSynchronizationService::class)->rebuild('ka');

    expect($result['swapped'])->not->toBeEmpty();
    $live = app(SearchIndexManager::class)->uid(SearchIndexType::Variants, 'ka');
    expect($gateway->indexExists($live))->toBeTrue()
        ->and($gateway->documentCount($live))->toBeGreaterThan(0);

    $previous = collect($gateway->indexes)->keys()->first(fn (string $uid): bool => str_contains($uid, '_rebuild_'));
    expect($previous)->not->toBeNull();
});
