<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Payments;

use Illuminate\Foundation\Http\FormRequest;

final class SimulateTestPaymentRequest extends FormRequest
{
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
            'outcome' => ['required', 'string', 'in:success,failure,failed,cancelled,processing,amount_mismatch,currency_mismatch,late_success'],
        ];
    }

    public function outcome(): string
    {
        return (string) $this->validated('outcome');
    }
}
