<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Inventory;

use App\Domains\Inventory\Enums\InventoryReasonCode;
use App\Http\Support\RequiresIdempotencyKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReconcileStockCountRequest extends FormRequest
{
    use RequiresIdempotencyKey;

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
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'counted_quantity' => ['required', 'integer', 'min:0'],
            'reason_code' => ['required', Rule::enum(InventoryReasonCode::class)],
            'note' => ['nullable', 'string', 'max:1000'],
            'expected_version' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
