<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Pricing\Models\PriceList;
use App\Domains\Pricing\Models\VariantPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VariantPrice>
 */
final class VariantPriceFactory extends Factory
{
    protected $model = VariantPrice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'price_list_id' => PriceList::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'version' => 0,
        ];
    }
}
