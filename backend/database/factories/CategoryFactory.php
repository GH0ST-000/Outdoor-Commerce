<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Catalog\Enums\CatalogStatus;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\CategoryTranslation;
use App\Domains\Catalog\Support\CatalogSlug;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'parent_id' => null,
            'status' => CatalogStatus::Active,
            'sort_order' => 0,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Category $category): void {
            if ($category->translations()->exists()) {
                return;
            }

            $name = 'კატეგორია-'.$category->id;
            CategoryTranslation::query()->create([
                'category_id' => $category->id,
                'locale' => 'ka',
                'name' => $name,
                'slug' => CatalogSlug::normalize('category-'.$category->id),
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
