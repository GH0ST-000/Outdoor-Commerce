<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Products;

use App\Domains\Catalog\Enums\ProductVariantStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class GenerateProductVariantsRequest extends FormRequest
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
            'axes' => ['required', 'array', 'min:1'],
            'axes.*.attribute_id' => ['required', 'integer', 'distinct'],
            'axes.*.attribute_value_ids' => ['required', 'array', 'min:1'],
            'axes.*.attribute_value_ids.*' => ['integer'],
            'status' => ['sometimes', Rule::in([ProductVariantStatus::Draft->value, ProductVariantStatus::Active->value])],
        ];
    }

    /**
     * @return array<int, list<int>>
     */
    public function selection(): array
    {
        /** @var array<int, array{attribute_id: int|string, attribute_value_ids: array<int, int|string>}> $axes */
        $axes = $this->validated('axes', []);
        $selection = [];

        foreach ($axes as $axis) {
            $selection[(int) $axis['attribute_id']] = array_values(array_map(
                'intval',
                $axis['attribute_value_ids'],
            ));
        }

        return $selection;
    }
}
