<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Catalog\Enums\ProductVariantStatus;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Support\Variants\VariantCombination;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $empty = VariantCombination::fromPairs([]);

        return [
            'product_id' => Product::factory(),
            'sku' => 'SKU-'.strtoupper($this->faker->unique()->bothify('??####')),
            'barcode' => null,
            'status' => ProductVariantStatus::Draft,
            'is_default' => false,
            'sort_order' => 0,
            'combination_hash' => $empty->hash,
            'combination_signature' => $empty->signature,
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn () => ['product_id' => $product->id]);
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => ProductVariantStatus::Active]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => ProductVariantStatus::Archived]);
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }

    public function sku(string $sku): static
    {
        return $this->state(fn () => ['sku' => strtoupper($sku)]);
    }

    /**
     * Assigns a multi-axis combination and keeps the identity columns in sync.
     *
     * @param  list<AttributeValue>  $values
     */
    public function withValues(array $values): static
    {
        $pairs = array_map(
            static fn (AttributeValue $value): array => [
                'attribute_id' => (int) $value->attribute_id,
                'attribute_value_id' => (int) $value->id,
            ],
            $values,
        );

        $combination = VariantCombination::fromPairs($pairs);

        return $this
            ->state(fn () => [
                'combination_hash' => $combination->hash,
                'combination_signature' => $combination->signature,
            ])
            ->afterCreating(function (ProductVariant $variant) use ($combination): void {
                foreach ($combination->pairs as $pair) {
                    $variant->combinationRows()->create([
                        'attribute_id' => $pair['attribute_id'],
                        'attribute_value_id' => $pair['attribute_value_id'],
                    ]);
                }
            });
    }
}
