<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Attributes;

use App\Domains\Catalog\Enums\AttributeStatus;
use App\Domains\Catalog\Enums\AttributeType;
use App\Domains\Catalog\Support\CatalogLocales;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreAttributeRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:64'],
            'type' => ['required', Rule::enum(AttributeType::class)],
            'status' => ['sometimes', Rule::enum(AttributeStatus::class)],
            'is_filterable' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'translations' => ['required', 'array', 'min:1'],
            'translations.*.locale' => ['required', 'string', Rule::in(CatalogLocales::all())],
            'translations.*.name' => ['required', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
