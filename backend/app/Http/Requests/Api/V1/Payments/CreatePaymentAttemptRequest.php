<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Payments;

use App\Http\Support\RequiresPaymentIdempotencyKey;
use Illuminate\Foundation\Http\FormRequest;

final class CreatePaymentAttemptRequest extends FormRequest
{
    use RequiresPaymentIdempotencyKey;

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
            'payment_method_code' => ['required', 'string', 'max:64'],
            'order_version' => ['nullable', 'integer', 'min:0'],
            'amount' => ['prohibited'],
            'amount_minor' => ['prohibited'],
            'currency' => ['prohibited'],
            'user_id' => ['prohibited'],
            'provider_payment_id' => ['prohibited'],
            'payment_status' => ['prohibited'],
            'order_status' => ['prohibited'],
            'return_url' => ['prohibited'],
            'callback_url' => ['prohibited'],
        ];
    }

    public function paymentMethodCode(): string
    {
        return (string) $this->validated('payment_method_code');
    }

    public function orderVersion(): ?int
    {
        $value = $this->validated('order_version');

        return is_numeric($value) ? (int) $value : null;
    }
}
