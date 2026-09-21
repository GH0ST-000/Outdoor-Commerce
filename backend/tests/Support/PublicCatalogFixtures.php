<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domains\Catalog\Enums\CatalogStatus;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Enums\ProductVariantStatus;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\Brand;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\MediaAttachment;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductTranslation;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\PublicApi\Services\PublicCatalogProjector;
use App\Domains\Inventory\Models\InventoryBalance;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Pricing\Enums\DiscountType;
use App\Domains\Pricing\Enums\PromotionTargetMode;
use App\Domains\Pricing\Enums\PromotionTargetType;
use App\Domains\Pricing\Models\PriceList;
use App\Domains\Pricing\Models\Promotion;
use App\Domains\Pricing\Models\PromotionTarget;
use Carbon\CarbonImmutable;

final class PublicCatalogFixtures
{
    public static function priceList(): PriceList
    {
        return PricingFixtures::retailGelList();
    }

    public static function warehouse(): Warehouse
    {
        $existing = Warehouse::query()->where('is_default', true)->first();

        return $existing ?? InventoryFixtures::defaultWarehouse();
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{
     *     product: Product,
     *     variant: ProductVariant,
     *     category: Category,
     *     brand: Brand|null,
     *     ka_slug: string,
     *     en_slug: string|null
     * }
     */
    public static function publicProduct(array $options = []): array
    {
        self::priceList();
        $warehouse = self::warehouse();

        $category = $options['category'] ?? self::category($options['category_options'] ?? []);
        $brand = array_key_exists('brand', $options)
            ? $options['brand']
            : self::brand($options['brand_options'] ?? []);

        $suffix = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $kaSlug = (string) ($options['ka_slug'] ?? 'produkti-'.$suffix);
        $enSlug = array_key_exists('en_slug', $options)
            ? $options['en_slug']
            : 'product-'.$suffix;

        $product = Product::factory()->create([
            'status' => $options['status'] ?? ProductStatus::Active,
            'published_at' => $options['published_at'] ?? now(),
            'brand_id' => $brand?->id,
            'primary_category_id' => $category->id,
            'is_featured' => (bool) ($options['featured'] ?? false),
            'sort_order' => (int) ($options['sort_order'] ?? 0),
            'model_number' => $options['model_number'] ?? 'MODEL-'.$suffix,
        ]);

        $product->translations()->where('locale', 'ka')->update([
            'name' => $options['ka_name'] ?? 'ტესტ პროდუქტი '.$suffix,
            'slug' => $kaSlug,
            'short_description' => $options['ka_short'] ?? 'მოკლე აღწერა',
            'description' => $options['ka_description'] ?? '<p>სრული აღწერა</p>',
            'seo_title' => $options['seo_title_ka'] ?? 'SEO '.$suffix,
            'seo_description' => $options['seo_description_ka'] ?? 'SEO აღწერა',
        ]);

        if (is_string($enSlug) && $enSlug !== '') {
            ProductTranslation::query()->create([
                'product_id' => $product->id,
                'locale' => 'en',
                'name' => $options['en_name'] ?? 'Test product '.$suffix,
                'slug' => $enSlug,
                'short_description' => $options['en_short'] ?? 'Short description',
                'description' => $options['en_description'] ?? '<p>Full description</p>',
                'seo_title' => $options['seo_title_en'] ?? 'SEO '.$suffix,
                'seo_description' => $options['seo_description_en'] ?? 'SEO description',
            ]);
        }

        $product->categories()->syncWithoutDetaching([
            $category->id => ['sort_order' => 0],
        ]);

        if (isset($options['extra_categories']) && is_array($options['extra_categories'])) {
            foreach ($options['extra_categories'] as $extra) {
                if ($extra instanceof Category) {
                    $product->categories()->syncWithoutDetaching([$extra->id => ['sort_order' => 1]]);
                }
            }
        }

        $variant = ProductVariant::factory()
            ->forProduct($product)
            ->active()
            ->default()
            ->sku((string) ($options['sku'] ?? 'PRD-'.$suffix))
            ->create();

        if (($options['priced'] ?? true) === true) {
            PricingFixtures::publishedPrice($variant, (int) ($options['price'] ?? 12999));
        }

        if (array_key_exists('stock', $options) || ($options['stock'] ?? 8) !== null) {
            InventoryBalance::factory()->create([
                'warehouse_id' => $warehouse->id,
                'product_variant_id' => $variant->id,
                'on_hand' => (int) ($options['stock'] ?? 8),
                'reserved' => (int) ($options['reserved'] ?? 0),
                'safety_stock' => (int) ($options['safety_stock'] ?? 0),
                'reorder_point' => (int) ($options['reorder_point'] ?? 0),
            ]);
        }

        if (($options['media'] ?? true) === true) {
            MediaAttachment::factory()
                ->forProduct($product)
                ->ready()
                ->primary()
                ->withKaEnAlt('ოპტიკური სამიზნე', 'Rifle scope')
                ->create();
        }

        $product->refresh();
        $variant->refresh();
        self::rebuild($product->id);

        return [
            'product' => $product->fresh(['translations', 'variants', 'brand', 'primaryCategory']) ?? $product,
            'variant' => $variant->fresh() ?? $variant,
            'category' => $category,
            'brand' => $brand,
            'ka_slug' => $kaSlug,
            'en_slug' => is_string($enSlug) ? $enSlug : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public static function category(array $options = []): Category
    {
        $parent = $options['parent'] ?? null;
        $category = Category::factory()->create([
            'parent_id' => $parent instanceof Category ? $parent->id : null,
            'status' => $options['status'] ?? CatalogStatus::Active,
            'sort_order' => (int) ($options['sort_order'] ?? 0),
        ]);

        $kaSlug = (string) ($options['ka_slug'] ?? 'kategoria-'.$category->id);
        $category->translations()->where('locale', 'ka')->update([
            'name' => $options['ka_name'] ?? 'კატეგორია '.$category->id,
            'slug' => $kaSlug,
        ]);

        if (isset($options['en_slug'])) {
            $category->translations()->create([
                'locale' => 'en',
                'name' => $options['en_name'] ?? 'Category '.$category->id,
                'slug' => (string) $options['en_slug'],
            ]);
        }

        return $category->fresh(['translations']) ?? $category;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public static function brand(array $options = []): Brand
    {
        $brand = Brand::factory()->create([
            'status' => $options['status'] ?? CatalogStatus::Active,
            'is_featured' => (bool) ($options['featured'] ?? false),
            'sort_order' => (int) ($options['sort_order'] ?? 0),
        ]);

        $brand->translations()->where('locale', 'ka')->update([
            'name' => $options['ka_name'] ?? 'ბრენდი '.$brand->id,
            'slug' => (string) ($options['ka_slug'] ?? 'brendi-'.$brand->id),
        ]);

        if (isset($options['en_slug'])) {
            $brand->translations()->create([
                'locale' => 'en',
                'name' => $options['en_name'] ?? 'Brand '.$brand->id,
                'slug' => (string) $options['en_slug'],
            ]);
        }

        return $brand->fresh(['translations']) ?? $brand;
    }

    /**
     * Color + size axes with two combinations that do not share a single false-positive match.
     *
     * @return array{
     *     product: Product,
     *     color: Attribute,
     *     size: Attribute,
     *     black: AttributeValue,
     *     green: AttributeValue,
     *     xl: AttributeValue,
     *     l: AttributeValue,
     *     black_xl: ProductVariant,
     *     green_l: ProductVariant,
     *     ka_slug: string
     * }
     */
    public static function multiAxisProduct(): array
    {
        $color = Attribute::factory()->active()->filterable()->color()->code('color')->create();
        $color->translations()->create(['locale' => 'en', 'name' => 'Color']);
        $size = Attribute::factory()->active()->filterable()->code('size')->create();
        $size->translations()->create(['locale' => 'en', 'name' => 'Size']);

        $black = AttributeValue::factory()->forAttribute($color)->active()->code('black')->withColor('#111111')->create();
        $black->translations()->create(['locale' => 'en', 'name' => 'Black']);
        $green = AttributeValue::factory()->forAttribute($color)->active()->code('forest_green')->withColor('#315B3A')->create();
        $green->translations()->create(['locale' => 'en', 'name' => 'Forest Green']);
        $xl = AttributeValue::factory()->forAttribute($size)->active()->code('xl')->create();
        $xl->translations()->create(['locale' => 'en', 'name' => 'XL']);
        $l = AttributeValue::factory()->forAttribute($size)->active()->code('l')->create();
        $l->translations()->create(['locale' => 'en', 'name' => 'L']);

        $base = self::publicProduct(['priced' => false, 'sku' => 'PRD-AXIS-BASE']);
        $product = $base['product'];
        $product->variantAttributes()->sync([
            $color->id => ['sort_order' => 0],
            $size->id => ['sort_order' => 1],
        ]);

        $base['variant']->update(['status' => ProductVariantStatus::Archived, 'is_default' => false]);

        $blackXl = ProductVariant::factory()
            ->forProduct($product)
            ->active()
            ->default()
            ->sku('PRD-AXIS-BXL')
            ->withValues([$black, $xl])
            ->create();
        PricingFixtures::publishedPrice($blackXl, 15000);

        $greenL = ProductVariant::factory()
            ->forProduct($product)
            ->active()
            ->sku('PRD-AXIS-GL')
            ->withValues([$green, $l])
            ->create();
        PricingFixtures::publishedPrice($greenL, 17000);

        InventoryBalance::factory()->create([
            'warehouse_id' => self::warehouse()->id,
            'product_variant_id' => $blackXl->id,
            'on_hand' => 5,
        ]);
        InventoryBalance::factory()->create([
            'warehouse_id' => self::warehouse()->id,
            'product_variant_id' => $greenL->id,
            'on_hand' => 0,
        ]);

        MediaAttachment::factory()->forVariant($blackXl)->ready()->primary()->withKaEnAlt('შავი', 'Black')->create();

        self::rebuild($product->id);

        return [
            'product' => $product->fresh(['translations', 'variants']) ?? $product,
            'color' => $color,
            'size' => $size,
            'black' => $black,
            'green' => $green,
            'xl' => $xl,
            'l' => $l,
            'black_xl' => $blackXl->fresh() ?? $blackXl,
            'green_l' => $greenL->fresh() ?? $greenL,
            'ka_slug' => $base['ka_slug'],
        ];
    }

    public static function activePromotion(int $percentageBasisPoints = 1500, ?CarbonImmutable $endsAt = null): Promotion
    {
        $promo = Promotion::factory()->active()->create([
            'code' => 'autumn_'.strtolower(substr(bin2hex(random_bytes(3)), 0, 6)),
            'name' => 'Autumn offer',
            'discount_type' => DiscountType::Percentage,
            'percentage_basis_points' => $percentageBasisPoints,
            'starts_at' => CarbonImmutable::now('UTC')->subHour(),
            'ends_at' => $endsAt,
        ]);

        PromotionTarget::query()->create([
            'promotion_id' => $promo->id,
            'target_type' => PromotionTargetType::AllProducts,
            'target_id' => null,
            'mode' => PromotionTargetMode::Include,
        ]);

        return $promo;
    }

    public static function rebuild(int $productId): void
    {
        app(PublicCatalogProjector::class)->refreshProductGraph($productId);
    }
}
