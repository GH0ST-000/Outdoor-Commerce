<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Inventory;

use Illuminate\Foundation\Http\FormRequest;

final class AdminInventoryIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'warehouse_id' => ['sometimes', 'integer'],
            'product_id' => ['sometimes', 'integer'],
            'product_variant_id' => ['sometimes', 'integer'],
            'low_stock' => ['sometimes', 'boolean'],
            'out_of_stock' => ['sometimes', 'boolean'],
            'has_reservations' => ['sometimes', 'boolean'],
            'locale' => ['sometimes', 'string', 'max:5'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
            'sort' => ['sometimes', 'string'],
            'direction' => ['sometimes', 'in:asc,desc'],
        ];
    }
}
