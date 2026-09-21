<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Catalog;

use App\Domains\Catalog\Exceptions\PublicCatalogQueryException;
use App\Domains\Catalog\PublicApi\Data\PublicProductListFilterData;
use App\Domains\Catalog\PublicApi\Enums\PublicProductSort;
use App\Http\Support\ApiErrorResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class PublicProductIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxPage = (int) config('catalog.public.pagination.max_per_page', 48);
        $maxBrands = (int) config('catalog.public.filters.max_brands', 20);
        $maxGroups = (int) config('catalog.public.filters.max_attribute_groups', 8);
        $maxValues = (int) config('catalog.public.filters.max_values_per_attribute', 20);
        $maxQuery = (int) config('catalog.public.filters.max_query_length', 80);

        return [
            'category' => ['sometimes', 'nullable', 'string', 'max:255'],
            'include_descendants' => ['sometimes', 'boolean'],
            'brand' => ['sometimes', 'array', 'max:'.$maxBrands],
            'brand.*' => ['string', 'max:255'],
            'attribute' => ['sometimes', 'array', 'max:'.$maxGroups],
            'attribute.*' => ['array', 'max:'.$maxValues],
            'attribute.*.*' => ['string', 'max:64'],
            'min_price' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'max_price' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'in_stock' => ['sometimes', 'boolean'],
            'on_sale' => ['sometimes', 'boolean'],
            'featured' => ['sometimes', 'boolean'],
            'q' => ['sometimes', 'nullable', 'string', 'max:'.$maxQuery],
            'sort' => ['sometimes', 'string', 'in:'.implode(',', PublicProductSort::values())],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.$maxPage],
            'locale' => ['sometimes', 'string', 'max:8'],
            'currency' => ['sometimes', 'string', 'size:3'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $allowed = [
            'category', 'include_descendants', 'brand', 'attribute',
            'min_price', 'max_price', 'in_stock', 'on_sale', 'featured',
            'q', 'sort', 'page', 'per_page', 'locale', 'currency',
        ];
        $unknown = array_values(array_diff(array_keys($this->query()), $allowed));
        if ($unknown !== []) {
            throw new HttpResponseException(ApiErrorResponse::make(
                $this,
                'CATALOG_FILTER_INVALID',
                'The submitted catalog query is invalid.',
                422,
                ['filters' => ['Unsupported catalog filter: '.$unknown[0]]],
            ));
        }

        if ($this->query->has('q')) {
            $normalized = trim((string) $this->query('q'));
            if ($normalized === '') {
                throw new HttpResponseException(ApiErrorResponse::make(
                    $this,
                    'CATALOG_FILTER_INVALID',
                    'The submitted catalog query is invalid.',
                    422,
                    ['q' => ['Search query must not be empty.']],
                ));
            }
            $this->merge(['q' => $normalized]);
        }
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(ApiErrorResponse::make(
            $this,
            'CATALOG_FILTER_INVALID',
            'The submitted catalog query is invalid.',
            422,
            $validator->errors()->toArray(),
        ));
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $min = $this->integer('min_price');
            $max = $this->integer('max_price');
            if ($this->filled('min_price') && $this->filled('max_price') && $min > $max) {
                $validator->errors()->add('min_price', 'min_price cannot exceed max_price.');
            }
        });
    }

    public function filters(): PublicProductListFilterData
    {
        $brands = array_values(array_filter((array) $this->input('brand', [])));
        $attributes = [];
        foreach ((array) $this->input('attribute', []) as $code => $values) {
            if (! is_string($code) || $code === '') {
                throw PublicCatalogQueryException::invalidFilter();
            }
            $attributes[$code] = array_values(array_unique(array_map('strval', (array) $values)));
        }

        $q = $this->filled('q') ? trim((string) $this->input('q')) : null;

        return new PublicProductListFilterData(
            category: $this->filled('category') ? (string) $this->input('category') : null,
            brands: $brands,
            attributes: $attributes,
            minPrice: $this->filled('min_price') ? $this->integer('min_price') : null,
            maxPrice: $this->filled('max_price') ? $this->integer('max_price') : null,
            inStock: $this->has('in_stock') ? $this->boolean('in_stock') : null,
            onSale: $this->has('on_sale') ? $this->boolean('on_sale') : null,
            featured: $this->has('featured') ? $this->boolean('featured') : null,
            q: $q,
            sort: (string) $this->input('sort', PublicProductSort::Default->value),
            page: max(1, $this->integer('page') ?: 1),
            perPage: $this->integer('per_page') ?: (int) config('catalog.public.pagination.default_per_page', 10),
            includeDescendants: $this->has('include_descendants')
                ? $this->boolean('include_descendants')
                : (bool) config('catalog.public.include_category_descendants', true),
        );
    }
}
