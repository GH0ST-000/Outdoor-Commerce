<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Inventory;

use App\Domains\Inventory\Enums\InventoryReasonCode;
use App\Http\Support\RequiresIdempotencyKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReceiveInventoryRequest extends FormRequest
{
    use RequiresIdempotencyKey;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer', 'distinct', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'reason_code' => ['required', Rule::enum(InventoryReasonCode::class)],
            'reference_type' => ['nullable', 'string', 'max:64'],
            'reference_id' => ['nullable', 'string', 'max:128'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
