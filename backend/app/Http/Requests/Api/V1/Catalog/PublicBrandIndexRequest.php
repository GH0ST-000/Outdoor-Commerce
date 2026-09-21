<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Catalog;

use Illuminate\Foundation\Http\FormRequest;

final class PublicBrandIndexRequest extends FormRequest
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
        $maxPage = (int) config('catalog.public.pagination.max_per_page', 48);

        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.$maxPage],
            'locale' => ['sometimes', 'string', 'max:8'],
            'currency' => ['sometimes', 'string', 'size:3'],
        ];
    }
}
