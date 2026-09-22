<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Orders;

use App\Http\Support\RequiresOrderIdempotencyKey;
use Illuminate\Foundation\Http\FormRequest;

final class CreateOrderRequest extends FormRequest
{
    use RequiresOrderIdempotencyKey;

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
            'checkout_session_id' => ['required', 'uuid'],
            'quote_id' => ['required', 'uuid'],
            'checkout_version' => ['required', 'integer', 'min:0'],
            'price' => ['prohibited'],
            'discount' => ['prohibited'],
            'delivery_total' => ['prohibited'],
            'tax_total' => ['prohibited'],
            'grand_total' => ['prohibited'],
            'user_id' => ['prohibited'],
            'cart_id' => ['prohibited'],
            'order_number' => ['prohibited'],
            'payment_status' => ['prohibited'],
            'order_status' => ['prohibited'],
            'reservation_id' => ['prohibited'],
            'items_subtotal_minor' => ['prohibited'],
            'discount_total_minor' => ['prohibited'],
            'delivery_total_minor' => ['prohibited'],
            'tax_total_minor' => ['prohibited'],
            'grand_total_minor' => ['prohibited'],
        ];
    }

    public function checkoutSessionId(): string
    {
        return (string) $this->validated('checkout_session_id');
    }

    public function quoteId(): string
    {
        return (string) $this->validated('quote_id');
    }

    public function checkoutVersion(): int
    {
        return (int) $this->validated('checkout_version');
    }
}
