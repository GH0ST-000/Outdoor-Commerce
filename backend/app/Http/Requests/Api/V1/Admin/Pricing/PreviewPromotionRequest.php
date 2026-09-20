<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Pricing;

use App\Domains\Pricing\Enums\DiscountType;
use App\Domains\Pricing\Enums\PromotionStackingMode;
use App\Domains\Pricing\Enums\PromotionTargetMode;
use App\Domains\Pricing\Enums\PromotionTargetType;
use App\Domains\Pricing\Services\CurrencyCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PreviewPromotionRequest extends FormRequest
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
            'price_list_id' => ['sometimes', 'nullable', 'integer', 'exists:price_lists,id'],
            'variant_ids' => ['sometimes', 'array'],
            'variant_ids.*' => ['integer', 'exists:product_variants,id'],
            'promotion' => ['sometimes', 'nullable', 'array'],
            'promotion.code' => ['sometimes', 'string', 'max:64'],
            'promotion.name' => ['sometimes', 'string', 'max:255'],
            'promotion.description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'promotion.discount_type' => ['sometimes', Rule::enum(DiscountType::class)],
            'promotion.percentage_basis_points' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10000'],
            'promotion.fixed_amount_minor' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'promotion.currency_code' => ['sometimes', 'nullable', 'string', 'size:3', Rule::in($enabled)],
            'promotion.priority' => ['sometimes', 'integer', 'min:0'],
            'promotion.stacking_mode' => ['sometimes', Rule::enum(PromotionStackingMode::class)],
            'promotion.starts_at' => ['sometimes', 'date'],
            'promotion.ends_at' => ['sometimes', 'nullable', 'date'],
            'promotion.maximum_discount_minor' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'targets' => ['sometimes', 'nullable', 'array'],
            'targets.*.target_type' => ['required_with:targets', Rule::enum(PromotionTargetType::class)],
            'targets.*.target_id' => ['sometimes', 'nullable', 'integer'],
            'targets.*.mode' => ['required_with:targets', Rule::enum(PromotionTargetMode::class)],
        ];
    }
}
