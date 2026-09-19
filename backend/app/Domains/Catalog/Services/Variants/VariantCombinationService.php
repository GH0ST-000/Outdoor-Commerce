<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Services\Variants;

use App\Domains\Catalog\Enums\AttributeStatus;
use App\Domains\Catalog\Enums\AttributeValueStatus;
use App\Domains\Catalog\Enums\ProductVariantStatus;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Support\Variants\VariantCombination;
use Illuminate\Validation\ValidationException;

/**
 * Validates attribute/value pairs against a product's variant axes and builds
 * the canonical combination identity. Labels never participate in identity.
 */
final class VariantCombinationService
{
    /**
     * Ordered axis attribute IDs for a product.
     *
     * @return list<int>
     */
    public function axisIds(Product $product): array
    {
        $product->loadMissing('variantAttributes');

        return $product->variantAttributes
            ->map(static fn ($attribute): int => (int) $attribute->id)
            ->values()
            ->all();
    }

    /**
     * @param  list<array{attribute_id: int, attribute_value_id: int}>  $pairs
     * @param  bool  $isNewCombination  Reject non-active values only for freshly selected combinations.
     */
    public function build(
        Product $product,
        array $pairs,
        ProductVariantStatus $status,
        bool $isNewCombination = true,
        string $field = 'attribute_values',
    ): VariantCombination {
        $axisIds = $this->axisIds($product);

        if ($axisIds === [] && $pairs !== []) {
            throw ValidationException::withMessages([
                $field => ['This product has no variant axes, so its variant combination must be empty.'],
            ]);
        }

        $seen = [];
        foreach ($pairs as $index => $pair) {
            $attributeId = (int) $pair['attribute_id'];
            $valueId = (int) $pair['attribute_value_id'];

            if (isset($seen[$attributeId])) {
                throw ValidationException::withMessages([
                    "{$field}.{$index}.attribute_id" => ['Each attribute may appear only once in a combination.'],
                ]);
            }
            $seen[$attributeId] = true;

            if (! in_array($attributeId, $axisIds, true)) {
                throw ValidationException::withMessages([
                    "{$field}.{$index}.attribute_id" => ['This attribute is not a variant axis of the product.'],
                ]);
            }

            /** @var AttributeValue|null $value */
            $value = AttributeValue::withTrashed()
                ->with('attribute')
                ->whereKey($valueId)
                ->first();

            if ($value === null || (int) $value->attribute_id !== $attributeId) {
                throw ValidationException::withMessages([
                    "{$field}.{$index}.attribute_value_id" => ['The selected value does not belong to this attribute.'],
                ]);
            }

            if ($value->trashed()) {
                throw ValidationException::withMessages([
                    "{$field}.{$index}.attribute_value_id" => ['Deleted attribute values cannot be used.'],
                ]);
            }

            if ($isNewCombination && $value->status !== AttributeValueStatus::Active) {
                throw ValidationException::withMessages([
                    "{$field}.{$index}.attribute_value_id" => ['Only active attribute values can be assigned.'],
                ]);
            }

            $attribute = $value->attribute;
            if ($isNewCombination && ($attribute === null || $attribute->status !== AttributeStatus::Active)) {
                throw ValidationException::withMessages([
                    "{$field}.{$index}.attribute_id" => ['Only active attributes can be used as variant axes.'],
                ]);
            }

            if ($status === ProductVariantStatus::Active && $value->status !== AttributeValueStatus::Active) {
                throw ValidationException::withMessages([
                    "{$field}.{$index}.attribute_value_id" => ['An active variant requires active attribute values.'],
                ]);
            }
        }

        if ($status === ProductVariantStatus::Active) {
            $missing = array_values(array_diff($axisIds, array_keys($seen)));
            if ($missing !== []) {
                throw ValidationException::withMessages([
                    $field => ['An active variant needs exactly one value for every variant axis.'],
                ]);
            }
        }

        return VariantCombination::fromPairs(array_values(array_map(
            static fn (array $pair): array => [
                'attribute_id' => (int) $pair['attribute_id'],
                'attribute_value_id' => (int) $pair['attribute_value_id'],
            ],
            $pairs,
        )));
    }

    /**
     * Rejects a combination that another variant of the same product already owns.
     */
    public function assertUnique(
        Product $product,
        VariantCombination $combination,
        ?int $ignoreVariantId = null,
        string $field = 'attribute_values',
    ): void {
        /** @var ProductVariant|null $existing */
        $existing = ProductVariant::withTrashed()
            ->where('product_id', $product->id)
            ->where('combination_hash', $combination->hash)
            ->when($ignoreVariantId !== null, fn ($query) => $query->whereKeyNot($ignoreVariantId))
            ->first();

        if ($existing === null) {
            return;
        }

        $message = $existing->trashed()
            ? "This combination belongs to archived variant [{$existing->id}]. Restore it instead of creating a duplicate."
            : "This combination is already used by variant [{$existing->id}].";

        throw ValidationException::withMessages([$field => [$message]]);
    }

    /**
     * Persists the combination rows for a variant, replacing any previous rows.
     */
    public function persist(ProductVariant $variant, VariantCombination $combination): void
    {
        $variant->combinationRows()->delete();

        foreach ($combination->pairs as $pair) {
            $variant->combinationRows()->create([
                'attribute_id' => $pair['attribute_id'],
                'attribute_value_id' => $pair['attribute_value_id'],
            ]);
        }

        $variant->combination_hash = $combination->hash;
        $variant->combination_signature = $combination->signature;
    }
}
