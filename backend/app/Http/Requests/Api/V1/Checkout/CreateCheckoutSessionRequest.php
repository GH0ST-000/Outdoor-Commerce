<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Checkout;

use App\Http\Support\RequiresCheckoutIdempotencyKey;
use Illuminate\Foundation\Http\FormRequest;

final class CreateCheckoutSessionRequest extends FormRequest
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
            'user_id' => ['prohibited'],
            'cart_id' => ['prohibited'],
            'items_subtotal_minor' => ['prohibited'],
            'grand_total_minor' => ['prohibited'],
        ];
    }
}
