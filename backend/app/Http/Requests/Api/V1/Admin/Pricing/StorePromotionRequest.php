<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Pricing;

use App\Domains\Pricing\Enums\DiscountType;
use App\Domains\Pricing\Enums\PromotionStackingMode;
use App\Domains\Pricing\Services\CurrencyCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StorePromotionRequest extends FormRequest
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
        $enabled = app(CurrencyCatalog::class)->enabledCodes();

        return [
            'code' => ['required', 'string', 'max:64', 'unique:promotions,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'discount_type' => ['required', Rule::enum(DiscountType::class)],
            'percentage_basis_points' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10000'],
            'fixed_amount_minor' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'currency_code' => ['sometimes', 'nullable', 'string', 'size:3', Rule::in($enabled)],
            'priority' => ['required', 'integer', 'min:0'],
            'stacking_mode' => ['required', Rule::enum(PromotionStackingMode::class)],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after:starts_at'],
            'maximum_discount_minor' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
