<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Shipping;

use App\Domains\Shipping\Enums\FulfillmentType;
use App\Http\Support\RequiresIdempotencyKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateShipmentRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'uuid'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'provider_code' => ['required', 'string', 'max:32'],
            'warehouse_code' => ['nullable', 'string', 'max:64'],
            'fulfillment_type' => ['nullable', Rule::enum(FulfillmentType::class)],
            'carrier_display_name' => ['nullable', 'string', 'max:128'],
            'tracking_number' => ['nullable', 'string', 'max:64'],
            'tracking_url' => ['nullable', 'string', 'max:2048'],
            'package_count' => ['nullable', 'integer', 'min:1', 'max:100'],
            'internal_note' => ['nullable', 'string', 'max:1000'],
            'status' => ['prohibited'],
            'fulfillment_status' => ['prohibited'],
            'warehouse_id' => ['prohibited'],
        ];
    }
}
