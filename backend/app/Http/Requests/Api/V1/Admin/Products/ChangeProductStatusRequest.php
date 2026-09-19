<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Products;

use App\Domains\Catalog\Enums\ProductStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ChangeProductStatusRequest extends FormRequest
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
            'status' => ['required', Rule::enum(ProductStatus::class)],
        ];
    }
}
