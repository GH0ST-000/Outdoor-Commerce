<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Products;

use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Support\CatalogLocales;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreProductRequest extends FormRequest
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
        $descMax = (int) config('catalog.description_max_length', 50000);
        $shortMax = (int) config('catalog.short_description_max_length', 500);

        return [
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'primary_category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
            'status' => ['sometimes', Rule::enum(ProductStatus::class)],
            'model_number' => ['nullable', 'string', 'max:255'],
            'manufacturer_part_number' => ['nullable', 'string', 'max:255'],
            'is_featured' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'translations' => ['required', 'array', 'min:1'],
            'translations.*.locale' => ['required', 'string', Rule::in(CatalogLocales::all())],
            'translations.*.name' => ['required', 'string', 'max:255'],
            'translations.*.slug' => ['required', 'string', 'max:255'],
            'translations.*.short_description' => ['nullable', 'string', 'max:'.$shortMax],
            'translations.*.description' => ['nullable', 'string', 'max:'.$descMax],
            'translations.*.seo_title' => ['nullable', 'string', 'max:70'],
            'translations.*.seo_description' => ['nullable', 'string', 'max:170'],
        ];
    }
}
