<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductTranslation;
use App\Domains\Catalog\Support\CatalogSlug;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductTranslation>
 */
class ProductTranslationFactory extends Factory
{
    protected $model = ProductTranslation::class;

    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'product_id' => Product::factory(),
            'locale' => 'en',
            'name' => $name,
            'slug' => CatalogSlug::normalize($name.'-'.fake()->unique()->numerify('###')),
            'short_description' => fake()->sentence(),
            'description' => '<p>'.fake()->paragraph().'</p>',
            'seo_title' => null,
            'seo_description' => null,
        ];
    }
}
