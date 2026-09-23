<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Checkout;

use App\Http\Support\RequiresCheckoutIdempotencyKey;
use Illuminate\Foundation\Http\FormRequest;

final class CreateCheckoutQuoteRequest extends FormRequest
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
            'checkout_version' => ['required', 'integer', 'min:0'],
            'cart_version' => ['required', 'integer', 'min:0'],
            'items_subtotal_minor' => ['prohibited'],
            'discount_total_minor' => ['prohibited'],
            'delivery_total_minor' => ['prohibited'],
            'tax_total_minor' => ['prohibited'],
            'grand_total_minor' => ['prohibited'],
            'unit_price_minor' => ['prohibited'],
        ];
    }

    public function checkoutVersion(): int
    {
        return (int) $this->validated('checkout_version');
    }

    public function cartVersion(): int
    {
        return (int) $this->validated('cart_version');
    }
}
