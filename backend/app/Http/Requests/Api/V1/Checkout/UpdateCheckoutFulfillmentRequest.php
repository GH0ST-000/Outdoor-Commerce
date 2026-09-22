<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Checkout;

use App\Http\Support\RequiresCheckoutIdempotencyKey;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateCheckoutFulfillmentRequest extends FormRequest
{
    use RequiresCheckoutIdempotencyKey;

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
            'method_code' => ['required', 'string', 'max:64'],
            'pickup_location_id' => ['nullable', 'uuid'],
            'checkout_version' => ['sometimes', 'integer', 'min:0'],
            'amount_minor' => ['prohibited'],
            'delivery_total_minor' => ['prohibited'],
            'rate_rule_id' => ['prohibited'],
        ];
    }

    public function checkoutVersion(): ?int
    {
        return $this->exists('checkout_version') ? (int) $this->input('checkout_version') : null;
    }

    public function methodCode(): string
    {
        return (string) $this->validated('method_code');
    }

    public function pickupLocationId(): ?string
    {
        $value = $this->validated('pickup_location_id') ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
