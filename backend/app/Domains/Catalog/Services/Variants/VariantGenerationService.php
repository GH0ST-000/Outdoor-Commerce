<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Services\Variants;

use App\Domains\Catalog\DTOs\Variants\VariantGenerationData;
use App\Domains\Catalog\Enums\AttributeValueStatus;
use App\Domains\Catalog\Exceptions\CombinationLimitExceededException;
use App\Domains\Catalog\Exceptions\VariantLimitExceededException;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Support\CatalogLocales;
use App\Domains\Catalog\Support\Variants\VariantCombination;
use Illuminate\Validation\ValidationException;

/**
 * Cartesian variant generation. `preview()` never writes; `generate()` runs
 * inside the caller's transaction and skips combinations that already exist.
 */
final class VariantGenerationService
{
    public function __construct(
        private readonly VariantCombinationService $combinationService,
        private readonly SkuService $skuService,
    ) {}

    public function limit(): int
    {
        return max(1, (int) config('catalog.variants.max_combinations_per_generation', 100));
    }

    public function maxVariantsPerProduct(): int
    {
        return max(1, (int) config('catalog.variants.max_variants_per_product', 500));
    }

    /**
     * Read-only plan of what a generate call would create.
     *
     * @return array{
     *     axes: list<array{attribute_id: int, code: string, name: string|null, value_ids: list<int>}>,
     *     limit: int,
     *     total_combinations: int,
     *     existing_count: int,
     *     new_count: int,
     *     exceeds_limit: bool,
     *     truncated: bool,
     *     combinations: list<array{signature: string, hash: string, exists: bool, pairs: list<array{attribute_id: int, attribute_value_id: int}>}>
     * }
     */
    public function preview(Product $product, VariantGenerationData $data): array
    {
        $axes = $this->resolveAxes($product, $data);
        $combinations = $this->cartesian($axes);
        $existingHashes = $this->existingHashes($product);
        $limit = $this->limit();

        $rows = [];
        $existingCount = 0;
        foreach ($combinations as $combination) {
            $exists = isset($existingHashes[$combination->hash]);
            $existingCount += $exists ? 1 : 0;

            if (count($rows) < $limit) {
                $rows[] = [
                    'signature' => $combination->signature,
                    'hash' => $combination->hash,
                    'exists' => $exists,
                    'pairs' => $combination->pairs,
                ];
            }
        }

        $total = count($combinations);

        return [
            'axes' => $axes,
            'limit' => $limit,
            'total_combinations' => $total,
            'existing_count' => $existingCount,
            'new_count' => $total - $existingCount,
            'exceeds_limit' => $total > $limit,
            'truncated' => $total > count($rows),
            'combinations' => $rows,
        ];
    }

    /**
     * Creates the missing combinations. Must run inside a transaction with the
     * product row locked; returns the created variants plus a summary.
     *
     * @return array{created: list<ProductVariant>, summary: array{requested: int, created: int, skipped: int, limit: int}}
     */
    public function generate(Product $product, VariantGenerationData $data, int $actorId): array
    {
        $axes = $this->resolveAxes($product, $data);
        $combinations = $this->cartesian($axes);
        $requested = count($combinations);
        $limit = $this->limit();

        if ($requested > $limit) {
            throw new CombinationLimitExceededException($requested, $limit);
        }

        $existingHashes = $this->existingHashes($product);
        $missing = array_values(array_filter(
            $combinations,
            static fn (VariantCombination $combination): bool => ! isset($existingHashes[$combination->hash]),
        ));

        if ($missing === []) {
            return [
                'created' => [],
                'summary' => [
                    'requested' => $requested,
                    'created' => 0,
                    'skipped' => $requested,
                    'limit' => $limit,
                ],
            ];
        }

        $this->assertProductVariantCapacity($product, count($missing));

        $skus = $this->skuService->generateMany($product, count($missing));
        $sortOrder = (int) (ProductVariant::withTrashed()
            ->where('product_id', $product->id)
            ->max('sort_order') ?? -1) + 1;

        $created = [];
        foreach ($missing as $index => $combination) {
            $variant = new ProductVariant;
            $variant->product_id = $product->id;
            $variant->sku = $skus[$index]->value;
            $variant->barcode = null;
            $variant->status = $data->status;
            $variant->is_default = false;
            $variant->sort_order = $sortOrder + $index;
            $variant->combination_hash = $combination->hash;
            $variant->combination_signature = $combination->signature;
            $variant->created_by = $actorId;
            $variant->updated_by = $actorId;
            $variant->save();

            $this->combinationService->persist($variant, $combination);
            $variant->save();

            $created[] = $variant;
        }

        return [
            'created' => $created,
            'summary' => [
                'requested' => $requested,
                'created' => count($created),
                'skipped' => $requested - count($created),
                'limit' => $limit,
            ],
        ];
    }

    public function assertProductVariantCapacity(Product $product, int $adding): void
    {
        $existing = ProductVariant::query()->where('product_id', $product->id)->count();
        $max = $this->maxVariantsPerProduct();

        if ($existing + $adding > $max) {
            throw new VariantLimitExceededException($existing + $adding, $max);
        }
    }

    /**
     * @return list<array{attribute_id: int, code: string, name: string|null, value_ids: list<int>}>
     */
    private function resolveAxes(Product $product, VariantGenerationData $data): array
    {
        $product->loadMissing('variantAttributes.translations');
        $axisAttributes = $product->variantAttributes;

        if ($axisAttributes->isEmpty()) {
            throw ValidationException::withMessages([
                'axes' => ['Assign variant axes to this product before generating variants.'],
            ]);
        }

        $selection = $data->selection;

        foreach (array_keys($selection) as $attributeId) {
            if ($axisAttributes->firstWhere('id', (int) $attributeId) === null) {
                throw ValidationException::withMessages([
                    'axes' => ["Attribute [{$attributeId}] is not a variant axis of this product."],
                ]);
            }
        }

        $locale = CatalogLocales::default();
        $resolved = [];

        foreach ($axisAttributes as $attribute) {
            $attributeId = (int) $attribute->id;
            $valueIds = array_values(array_unique(array_map('intval', $selection[$attributeId] ?? [])));

            if ($valueIds === []) {
                throw ValidationException::withMessages([
                    'axes' => ["Select at least one value for axis [{$attribute->code}]."],
                ]);
            }

            $values = AttributeValue::query()
                ->where('attribute_id', $attributeId)
                ->whereKey($valueIds)
                ->where('status', AttributeValueStatus::Active->value)
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            $unusable = array_values(array_diff($valueIds, $values));
            if ($unusable !== []) {
                throw ValidationException::withMessages([
                    'axes' => [
                        'Only active values belonging to the axis can be generated: ['
                            .implode(', ', $unusable).'].',
                    ],
                ]);
            }

            sort($values);

            $resolved[] = [
                'attribute_id' => $attributeId,
                'code' => (string) $attribute->code,
                'name' => $attribute->localizedName($locale),
                'value_ids' => $values,
            ];
        }

        usort($resolved, static fn (array $a, array $b): int => $a['attribute_id'] <=> $b['attribute_id']);

        return $resolved;
    }

    /**
     * @param  list<array{attribute_id: int, code: string, name: string|null, value_ids: list<int>}>  $axes
     * @return list<VariantCombination>
     */
    private function cartesian(array $axes): array
    {
        /** @var list<list<array{attribute_id: int, attribute_value_id: int}>> $rows */
        $rows = [[]];

        foreach ($axes as $axis) {
            $next = [];
            foreach ($rows as $row) {
                foreach ($axis['value_ids'] as $valueId) {
                    $next[] = array_merge($row, [[
                        'attribute_id' => $axis['attribute_id'],
                        'attribute_value_id' => $valueId,
                    ]]);
                }
            }
            $rows = $next;
        }

        return array_map(
            static fn (array $pairs): VariantCombination => VariantCombination::fromPairs($pairs),
            $rows,
        );
    }

    /**
     * Includes soft-deleted variants: their combination still occupies the
     * unique `(product_id, combination_hash)` slot.
     *
     * @return array<string, true>
     */
    private function existingHashes(Product $product): array
    {
        $hashes = [];

        foreach (ProductVariant::withTrashed()->where('product_id', $product->id)->pluck('combination_hash') as $hash) {
            $hashes[(string) $hash] = true;
        }

        return $hashes;
    }
}
