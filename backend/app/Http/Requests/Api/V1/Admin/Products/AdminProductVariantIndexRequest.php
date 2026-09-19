<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Products;

use App\Domains\Catalog\Enums\ProductVariantStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AdminProductVariantIndexRequest extends FormRequest
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
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'nullable', Rule::enum(ProductVariantStatus::class)],
            'is_default' => ['sometimes', 'nullable', 'boolean'],
            'attribute_id' => ['sometimes', 'nullable', 'array'],
            'attribute_id.*' => ['integer'],
            'attribute_value_id' => ['sometimes', 'nullable', 'array'],
            'attribute_value_id.*' => ['integer'],
            'include_deleted' => ['sometimes', 'nullable', 'boolean'],
            'sort' => ['sometimes', 'nullable', Rule::in(['created_at', 'updated_at', 'sort_order', 'sku', 'status'])],
            'direction' => ['sometimes', 'nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
