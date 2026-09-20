<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Inventory;

use Illuminate\Foundation\Http\FormRequest;

final class AdminWarehouseIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', 'string'],
            'is_default' => ['sometimes', 'nullable'],
            'include_deleted' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
            'sort' => ['sometimes', 'string'],
            'direction' => ['sometimes', 'in:asc,desc'],
        ];
    }
}
