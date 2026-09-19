<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Attributes;

use App\Domains\Catalog\Enums\AttributeValueStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AdminAttributeValueIndexRequest extends FormRequest
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
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'nullable', Rule::enum(AttributeValueStatus::class)],
            'locale' => ['sometimes', 'nullable', 'string', 'max:8'],
            'include_deleted' => ['sometimes', 'nullable', 'boolean'],
            'sort' => ['sometimes', 'nullable', Rule::in(['created_at', 'updated_at', 'sort_order', 'code', 'status'])],
            'direction' => ['sometimes', 'nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
