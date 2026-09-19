<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Products;

use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Support\CatalogLocales;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateProductRequest extends FormRequest
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
            'brand_id' => ['sometimes', 'nullable', 'integer', 'exists:brands,id'],
            'primary_category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
            'status' => ['sometimes', Rule::enum(ProductStatus::class)],
            'model_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'manufacturer_part_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_featured' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'sync_translations' => ['sometimes', 'boolean'],
            'remove_english' => ['sometimes', 'boolean'],
            'translations' => ['sometimes', 'array'],
            'translations.*.locale' => ['required_with:translations', 'string', Rule::in(CatalogLocales::all())],
            'translations.*.name' => ['required_with:translations', 'string', 'max:255'],
            'translations.*.slug' => ['required_with:translations', 'string', 'max:255'],
            'translations.*.short_description' => ['nullable', 'string', 'max:'.$shortMax],
            'translations.*.description' => ['nullable', 'string', 'max:'.$descMax],
            'translations.*.seo_title' => ['nullable', 'string', 'max:70'],
            'translations.*.seo_description' => ['nullable', 'string', 'max:170'],
        ];
    }
}
