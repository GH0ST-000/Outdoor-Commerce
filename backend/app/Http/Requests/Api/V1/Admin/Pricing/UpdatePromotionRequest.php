<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Pricing;

use App\Domains\Pricing\Enums\DiscountType;
use App\Domains\Pricing\Enums\PromotionStackingMode;
use App\Domains\Pricing\Services\CurrencyCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdatePromotionRequest extends FormRequest
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
        $id = $this->route('promotion');
        $ignoreId = is_object($id) ? $id->id : $id;

        return [
            'code' => ['sometimes', 'string', 'max:64', Rule::unique('promotions', 'code')->ignore($ignoreId)],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'discount_type' => ['sometimes', Rule::enum(DiscountType::class)],
            'percentage_basis_points' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10000'],
            'fixed_amount_minor' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'currency_code' => ['sometimes', 'nullable', 'string', 'size:3', Rule::in($enabled)],
            'priority' => ['sometimes', 'integer', 'min:0'],
            'stacking_mode' => ['sometimes', Rule::enum(PromotionStackingMode::class)],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
            'maximum_discount_minor' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
