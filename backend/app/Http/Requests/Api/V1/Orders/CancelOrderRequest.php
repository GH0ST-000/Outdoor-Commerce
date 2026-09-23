<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Orders;

use App\Http\Support\RequiresOrderIdempotencyKey;
use Illuminate\Foundation\Http\FormRequest;

final class CancelOrderRequest extends FormRequest
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
            'status' => ['prohibited'],
            'payment_status' => ['prohibited'],
        ];
    }
}
