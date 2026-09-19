<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Products;

use Illuminate\Foundation\Http\FormRequest;

final class ArchiveProductVariantRequest extends FormRequest
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
            'replacement_variant_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }
}
