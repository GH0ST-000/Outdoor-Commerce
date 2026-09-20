<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Pricing;

use App\Domains\Pricing\Enums\PriceListStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StorePriceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64', 'unique:price_lists,code'],
            'name' => ['required', 'string', 'max:255'],
            'currency_code' => ['required', 'string', 'size:3'],
            'status' => ['sometimes', Rule::enum(PriceListStatus::class)],
            'is_default' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'prices_include_tax' => ['sometimes', 'boolean'],
        ];
    }
}
