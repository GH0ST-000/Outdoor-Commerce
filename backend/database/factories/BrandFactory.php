<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Catalog\Enums\CatalogStatus;
use App\Domains\Catalog\Models\Brand;
use App\Domains\Catalog\Models\BrandTranslation;
use App\Domains\Catalog\Support\CatalogSlug;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
{
    protected $model = Brand::class;

    public function definition(): array
    {
        return [
            'status' => CatalogStatus::Active,
            'sort_order' => 0,
            'is_featured' => false,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Brand $brand): void {
            if ($brand->translations()->exists()) {
                return;
            }

            BrandTranslation::query()->create([
                'brand_id' => $brand->id,
                'locale' => 'ka',
                'name' => 'ბრენდი-'.$brand->id,
                'slug' => CatalogSlug::normalize('brand-'.$brand->id),
            ]);
        });
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => CatalogStatus::Draft]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => CatalogStatus::Archived]);
    }
}
