<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Pricing;

use App\Domains\Pricing\Enums\PricePeriodStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AdminPriceIndexRequest extends FormRequest
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
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'price_list_id' => ['sometimes', 'nullable', 'integer', 'exists:price_lists,id'],
            'product_id' => ['sometimes', 'nullable', 'integer'],
            'product_variant_id' => ['sometimes', 'nullable', 'integer'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'period_status' => ['sometimes', 'nullable', Rule::enum(PricePeriodStatus::class)],
            'pricing_ready' => ['sometimes', 'nullable', 'boolean'],
            'locale' => ['sometimes', 'nullable', 'string', 'max:8'],
            'sort' => ['sometimes', 'string', 'max:64'],
            'direction' => ['sometimes', 'in:asc,desc'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
