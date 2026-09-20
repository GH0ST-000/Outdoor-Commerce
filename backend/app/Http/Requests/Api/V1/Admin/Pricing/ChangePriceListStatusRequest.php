<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Pricing;

use App\Domains\Pricing\Enums\PriceListStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ChangePriceListStatusRequest extends FormRequest
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
            'status' => ['required', Rule::enum(PriceListStatus::class)],
            'replacement_default_price_list_id' => ['sometimes', 'nullable', 'integer', 'exists:price_lists,id'],
        ];
    }
}
