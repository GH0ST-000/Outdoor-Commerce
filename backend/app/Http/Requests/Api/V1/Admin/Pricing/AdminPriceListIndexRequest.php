<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Pricing;

use App\Domains\Pricing\Enums\PriceListStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AdminPriceListIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', Rule::enum(PriceListStatus::class)],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'currency_code' => ['sometimes', 'nullable', 'string', 'size:3'],
            'is_default' => ['sometimes', 'nullable'],
            'include_deleted' => ['sometimes', 'nullable'],
            'sort' => ['sometimes', 'string', 'max:64'],
            'direction' => ['sometimes', 'in:asc,desc'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
