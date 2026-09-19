<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Enums\ProductVariantStatus;
use App\Domains\Catalog\Models\Brand;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductTranslation;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Support\CatalogSlug;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'brand_id' => null,
            'primary_category_id' => null,
            'status' => ProductStatus::Draft,
            'model_number' => null,
            'manufacturer_part_number' => null,
            'is_featured' => false,
            'sort_order' => 0,
            'published_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Product $product): void {
            if ($product->translations()->exists()) {
                return;
            }

            ProductTranslation::query()->create([
                'product_id' => $product->id,
                'locale' => 'ka',
                'name' => 'პროდუქტი-'.$product->id,
                'slug' => CatalogSlug::normalize('product-'.$product->id),
                'short_description' => 'მოკლე აღწერა',
                'description' => '<p>აღწერა</p>',
            ]);
        });
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => ProductStatus::Draft]);
    }

    public function active(): static
    {
        return $this->withDefaultVariant()->state(fn () => [
            'status' => ProductStatus::Active,
            'published_at' => now(),
        ]);
    }

    /**
     * Gives the product the single empty-combination variant that a zero-axis
     * product needs to be activation-ready.
     */
    public function withDefaultVariant(ProductVariantStatus $status = ProductVariantStatus::Active): static
    {
        return $this->afterCreating(function (Product $product) use ($status): void {
            if ($product->variants()->exists()) {
                return;
            }

            ProductVariant::factory()
                ->forProduct($product)
                ->default()
                ->create(['status' => $status]);

            $product->unsetRelation('variants');
        });
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => ProductStatus::Archived]);
    }

    public function featured(): static
    {
        return $this->state(fn () => ['is_featured' => true]);
    }

    public function branded(?Brand $brand = null): static
    {
        return $this->state(fn () => [
            'brand_id' => $brand instanceof Brand ? $brand->id : Brand::factory(),
        ]);
    }

    public function withPrimaryCategory(?Category $category = null): static
    {
        return $this->afterCreating(function (Product $product) use ($category): void {
            $cat = $category ?? Category::factory()->create();
            $product->primary_category_id = $cat->id;
            $product->save();
            $product->categories()->syncWithoutDetaching([
                $cat->id => ['sort_order' => 0],
            ]);
        });
    }

    public function readyToActivate(): static
    {
        return $this->withPrimaryCategory()->withDefaultVariant()->state(fn () => [
            'status' => ProductStatus::Draft,
        ]);
    }
}
