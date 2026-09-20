<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Inventory;

use App\Domains\Inventory\Enums\WarehouseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ChangeWarehouseStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(WarehouseStatus::class)],
            'replacement_default_warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
        ];
    }
}
