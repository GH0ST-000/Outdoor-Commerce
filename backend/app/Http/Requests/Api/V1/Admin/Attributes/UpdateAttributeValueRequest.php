<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Attributes;

use App\Domains\Catalog\Enums\AttributeValueStatus;
use App\Domains\Catalog\Support\CatalogLocales;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateAttributeValueRequest extends FormRequest
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
            'code' => ['sometimes', 'string', 'max:64'],
            'status' => ['sometimes', Rule::enum(AttributeValueStatus::class)],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'color_hex' => ['sometimes', 'nullable', 'string', 'max:7'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'translations' => ['sometimes', 'array', 'min:1'],
            'translations.*.locale' => ['required_with:translations', 'string', Rule::in(CatalogLocales::all())],
            'translations.*.name' => ['required_with:translations', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
