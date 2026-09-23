<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Checkout;

use App\Http\Support\RequiresCheckoutIdempotencyKey;
use Illuminate\Foundation\Http\FormRequest;

final class CancelCheckoutSessionRequest extends FormRequest
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
            'checkout_version' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function checkoutVersion(): ?int
    {
        return $this->exists('checkout_version') ? (int) $this->input('checkout_version') : null;
    }
}
