<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Pricing;

use App\Domains\Pricing\Enums\DiscountType;
use App\Domains\Pricing\Enums\PromotionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AdminPromotionIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', Rule::enum(PromotionStatus::class)],
            'discount_type' => ['sometimes', 'nullable', Rule::enum(DiscountType::class)],
            'lifecycle' => ['sometimes', 'nullable', 'string', Rule::in([
                'draft', 'scheduled', 'live', 'paused', 'expired', 'archived',
            ])],
            'stacking_mode' => ['sometimes', 'nullable', 'string'],
            'target_type' => ['sometimes', 'nullable', 'string'],
            'starts_before' => ['sometimes', 'nullable', 'date'],
            'starts_after' => ['sometimes', 'nullable', 'date'],
            'ends_before' => ['sometimes', 'nullable', 'date'],
            'ends_after' => ['sometimes', 'nullable', 'date'],
            'include_deleted' => ['sometimes', 'nullable', 'boolean'],
            'sort' => ['sometimes', 'string', 'max:64'],
            'direction' => ['sometimes', 'in:asc,desc'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
