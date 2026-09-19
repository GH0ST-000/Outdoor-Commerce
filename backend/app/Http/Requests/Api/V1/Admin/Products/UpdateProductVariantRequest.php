<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Products;

use App\Domains\Catalog\Enums\ProductVariantStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateProductVariantRequest extends FormRequest
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
            'sku' => ['sometimes', 'string', 'max:64'],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:32'],
            'status' => ['sometimes', Rule::enum(ProductVariantStatus::class)],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_default' => ['sometimes', 'boolean'],
            'attribute_values' => ['sometimes', 'array'],
            'attribute_values.*.attribute_id' => ['required_with:attribute_values', 'integer'],
            'attribute_values.*.attribute_value_id' => ['required_with:attribute_values', 'integer'],
        ];
    }
}
