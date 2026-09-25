<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Species;

use App\Domains\Hunting\Enums\SpeciesAliasType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreSpeciesAliasRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:160'],
            'locale' => ['nullable', 'in:ka,en'],
            'type' => ['required', Rule::enum(SpeciesAliasType::class)],
            'is_searchable' => ['sometimes', 'boolean'],
            'is_public' => ['sometimes', 'boolean'],
            'source_id' => ['nullable'],
            'source_public_id' => ['nullable', 'uuid'],
        ];
    }
}
