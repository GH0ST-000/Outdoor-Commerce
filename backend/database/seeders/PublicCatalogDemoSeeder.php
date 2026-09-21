<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\MediaAttachment;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductTranslation;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\PublicApi\Services\PublicCatalogProjector;
use App\Domains\Catalog\Support\CatalogSlug;
use App\Domains\Inventory\Models\InventoryBalance;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Pricing\Enums\PriceListStatus;
use App\Domains\Pricing\Enums\PricePeriodStatus;
use App\Domains\Pricing\Models\PriceList;
use App\Domains\Pricing\Models\PricePeriod;
use App\Domains\Pricing\Models\VariantPrice;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Promotes CatalogDemoSeeder drafts into public storefront products.
 *
 * CatalogDemoSeeder stays draft/unpriced by design. Local next-dev pages need an
 * active GEL list, ready media, published prices, and rebuilt projections.
 */
final class PublicCatalogDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $this->call(CatalogDemoSeeder::class);
        $this->call(PricingDemoSeeder::class);

        $list = $this->priceList();
        $warehouse = $this->warehouse();
        $projector = app(PublicCatalogProjector::class);

        $products = Product::query()
            ->whereIn('model_number', ['DEMO-OPTIC-1', 'DEMO-KNIFE-1'])
            ->with('variants')
            ->get();

        foreach ($products as $product) {
            $product->status = ProductStatus::Active;
            $product->published_at ??= now();
            $product->save();

            $this->englishTranslation($product);
            $this->readyMedia($product);

            foreach ($product->variants as $variantIndex => $variant) {
                $this->publishedPrice($list, $variant, $product->model_number === 'DEMO-KNIFE-1' ? 4_999 : 12_999 + ($variantIndex * 1_000));
                $this->stock($warehouse, $variant, max(0, 12 - ($variantIndex * 3)));
            }

            $projector->refreshProductGraph((int) $product->id);
        }
    }

    private function priceList(): PriceList
    {
        return PriceList::query()->firstOrCreate(
            ['code' => 'retail_gel'],
            [
                'name' => 'Retail GEL',
                'currency_code' => 'GEL',
                'status' => PriceListStatus::Active,
                'is_default' => true,
                'priority' => 0,
                'prices_include_tax' => true,
            ],
        );
    }

    private function warehouse(): Warehouse
    {
        $existing = Warehouse::query()->where('is_default', true)->first()
            ?? Warehouse::query()->where('code', 'TBS-MAIN')->first();

        return $existing ?? Warehouse::factory()->default()->create([
            'code' => 'TBS-MAIN',
            'name' => 'Tbilisi Main Warehouse',
        ]);
    }

    private function englishTranslation(Product $product): void
    {
        $en = match ($product->model_number) {
            'DEMO-KNIFE-1' => [
                'name' => 'Demo hunting knife',
                'slug' => CatalogSlug::normalize('demo-hunting-knife'),
                'short_description' => 'Demo short description',
                'description' => '<p>Demo description for a hunting knife.</p>',
            ],
            default => [
                'name' => 'Demo hunting optics',
                'slug' => CatalogSlug::normalize('demo-hunting-optics'),
                'short_description' => 'Demo short description',
                'description' => '<p>Demo description for hunting optics.</p>',
            ],
        };

        ProductTranslation::query()->updateOrCreate(
            ['product_id' => $product->id, 'locale' => 'en'],
            $en,
        );
    }

    private function readyMedia(Product $product): void
    {
        $product->loadMissing('mediaAttachments.asset');
        if ($product->mediaAttachments->isNotEmpty()) {
            return;
        }

        MediaAttachment::factory()
            ->forProduct($product)
            ->ready()
            ->primary()
            ->withKaEnAlt(
                $product->model_number === 'DEMO-KNIFE-1' ? 'სანადირო დანა' : 'ოპტიკური სამიზნე',
                $product->model_number === 'DEMO-KNIFE-1' ? 'Hunting knife' : 'Rifle scope',
            )
            ->create();
    }

    private function publishedPrice(PriceList $list, ProductVariant $variant, int $amountMinor): void
    {
        $aggregate = VariantPrice::query()->firstOrCreate(
            [
                'price_list_id' => $list->id,
                'product_variant_id' => $variant->id,
            ],
            ['version' => 0],
        );

        if ($aggregate->periods()->exists()) {
            return;
        }

        PricePeriod::query()->create([
            'variant_price_id' => $aggregate->id,
            'amount_minor' => $amountMinor,
            'status' => PricePeriodStatus::Published,
            'starts_at' => CarbonImmutable::now('UTC')->subDays(7),
            'ends_at' => null,
            'published_at' => CarbonImmutable::now('UTC'),
        ]);
    }

    private function stock(Warehouse $warehouse, ProductVariant $variant, int $onHand): void
    {
        InventoryBalance::query()->firstOrCreate(
            [
                'warehouse_id' => $warehouse->id,
                'product_variant_id' => $variant->id,
            ],
            [
                'on_hand' => $onHand,
                'reserved' => 0,
                'safety_stock' => 0,
                'reorder_point' => 0,
            ],
        );
    }
}
