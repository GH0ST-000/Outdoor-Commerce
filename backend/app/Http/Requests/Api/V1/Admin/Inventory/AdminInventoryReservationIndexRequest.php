<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Inventory;

use Illuminate\Foundation\Http\FormRequest;

final class AdminInventoryReservationIndexRequest extends FormRequest
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
            'status' => ['sometimes', 'string'],
            'warehouse_id' => ['sometimes', 'integer'],
            'product_variant_id' => ['sometimes', 'integer'],
            'reference_type' => ['sometimes', 'string'],
            'reference_id' => ['sometimes', 'string'],
            'expires_before' => ['sometimes', 'date'],
            'expires_after' => ['sometimes', 'date'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
            'sort' => ['sometimes', 'string'],
            'direction' => ['sometimes', 'in:asc,desc'],
        ];
    }
}
