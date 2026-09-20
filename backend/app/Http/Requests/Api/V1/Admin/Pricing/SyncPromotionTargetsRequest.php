<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Pricing;

use App\Domains\Pricing\Enums\PromotionTargetMode;
use App\Domains\Pricing\Enums\PromotionTargetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SyncPromotionTargetsRequest extends FormRequest
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
            'targets' => ['required', 'array', 'min:1'],
            'targets.*.target_type' => ['required', Rule::enum(PromotionTargetType::class)],
            'targets.*.target_id' => ['sometimes', 'nullable', 'integer'],
            'targets.*.mode' => ['required', Rule::enum(PromotionTargetMode::class)],
        ];
    }
}
