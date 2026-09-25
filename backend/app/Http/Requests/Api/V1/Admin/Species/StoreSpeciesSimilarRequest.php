<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Species;

use App\Domains\Hunting\Enums\SimilarSpeciesRelationType;
use App\Domains\Hunting\Enums\SpeciesVerificationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreSpeciesSimilarRequest extends FormRequest
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
            'similar_species_id' => ['required', 'uuid'],
            'relationship_type' => ['required', Rule::enum(SimilarSpeciesRelationType::class)],
            'confidence' => ['sometimes', Rule::enum(SpeciesVerificationStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'source_id' => ['nullable'],
            'source_public_id' => ['nullable', 'uuid'],
        ];
    }
}
