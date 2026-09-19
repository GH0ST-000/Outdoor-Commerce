<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Attributes;

use App\Domains\Catalog\Enums\AttributeValueStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ChangeAttributeValueStatusRequest extends FormRequest
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
            'status' => ['required', Rule::enum(AttributeValueStatus::class)],
        ];
    }
}
