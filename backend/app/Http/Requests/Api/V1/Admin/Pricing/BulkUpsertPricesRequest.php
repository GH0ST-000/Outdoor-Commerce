<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Pricing;

use Illuminate\Foundation\Http\FormRequest;

final class BulkUpsertPricesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $max = (int) config('pricing.bulk.max_rows', 100);

        return [
            'publish' => ['sometimes', 'boolean'],
            'items' => ['required', 'array', 'min:1', 'max:'.$max],
            'items.*.price_list_id' => ['required', 'integer', 'exists:price_lists,id'],
            'items.*.product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.amount_minor' => ['required', 'integer', 'min:0'],
            'items.*.starts_at' => ['sometimes', 'nullable', 'date'],
            'items.*.expected_version' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
