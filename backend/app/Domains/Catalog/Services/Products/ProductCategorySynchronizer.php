<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Services\Products;

use App\Domains\Catalog\Enums\CatalogStatus;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use Illuminate\Validation\ValidationException;

final class ProductCategorySynchronizer
{
    /**
     * @param  list<int>  $categoryIds
     * @return array{previous: list<int>, next: list<int>}
     */
    public function sync(Product $product, ?int $primaryCategoryId, array $categoryIds, ProductStatus $status): array
    {
        $categoryIds = array_values(array_unique(array_map('intval', $categoryIds)));

        if ($primaryCategoryId !== null && ! in_array($primaryCategoryId, $categoryIds, true)) {
            throw ValidationException::withMessages([
                'primary_category_id' => ['The primary category must be included in category assignments.'],
            ]);
        }

        if ($status === ProductStatus::Active && ($primaryCategoryId === null || $categoryIds === [])) {
            throw ValidationException::withMessages([
                'category_ids' => ['An active product must have at least one category and a primary category.'],
            ]);
        }

        if ($categoryIds !== []) {
            $categories = Category::query()
                ->whereIn('id', $categoryIds)
                ->get();

            if ($categories->count() !== count($categoryIds)) {
                throw ValidationException::withMessages([
                    'category_ids' => ['One or more categories do not exist.'],
                ]);
            }

            foreach ($categories as $category) {
                if ($category->status === CatalogStatus::Archived) {
                    throw ValidationException::withMessages([
                        'category_ids' => ['Archived categories cannot be assigned.'],
                    ]);
                }
            }

            if ($primaryCategoryId !== null && $status === ProductStatus::Active) {
                $primary = $categories->firstWhere('id', $primaryCategoryId);
                if ($primary === null || $primary->status !== CatalogStatus::Active) {
                    throw ValidationException::withMessages([
                        'primary_category_id' => ['An active primary category is required.'],
                    ]);
                }
            }
        }

        $previous = $product->categories()->pluck('categories.id')->map(fn ($id) => (int) $id)->all();

        $syncPayload = [];
        foreach ($categoryIds as $index => $id) {
            $syncPayload[$id] = ['sort_order' => $index];
        }

        $product->categories()->sync($syncPayload);
        $product->primary_category_id = $primaryCategoryId;
        $product->save();

        return [
            'previous' => $previous,
            'next' => $categoryIds,
        ];
    }
}
