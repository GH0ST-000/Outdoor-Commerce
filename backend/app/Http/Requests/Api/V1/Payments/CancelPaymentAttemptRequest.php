<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Payments;

use App\Http\Support\RequiresPaymentIdempotencyKey;
use Illuminate\Foundation\Http\FormRequest;

final class CancelPaymentAttemptRequest extends FormRequest
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
        return [];
    }
}
