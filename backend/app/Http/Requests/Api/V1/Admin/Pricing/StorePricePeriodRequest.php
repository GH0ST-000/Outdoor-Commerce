<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Pricing;

use Illuminate\Foundation\Http\FormRequest;

final class StorePricePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount_minor' => ['required', 'integer', 'min:0'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after:starts_at'],
            'expected_version' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
