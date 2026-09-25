<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Shipping;

use App\Domains\Shipping\Enums\ShipmentExceptionCode;
use App\Http\Support\RequiresIdempotencyKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TransitionShipmentRequest extends FormRequest
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
            'expected_version' => ['required', 'integer', 'min:1'],
            'items' => ['nullable', 'array'],
            'items.*.order_item_id' => ['required_with:items', 'uuid'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:0'],
            'tracking_number' => ['nullable', 'string', 'max:64'],
            'tracking_url' => ['nullable', 'string', 'max:2048'],
            'carrier_display_name' => ['nullable', 'string', 'max:128'],
            'exception_code' => ['nullable', Rule::enum(ShipmentExceptionCode::class)],
            'internal_note' => ['nullable', 'string', 'max:1000'],
            'location_label' => ['nullable', 'string', 'max:128'],
            'status' => ['prohibited'],
        ];
    }

    public function expectedVersion(): int
    {
        return (int) $this->validated('expected_version');
    }
}
