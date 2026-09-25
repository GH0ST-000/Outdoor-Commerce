<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Species;

use App\Domains\Hunting\Enums\KnowledgeSourceType;
use App\Domains\Hunting\Enums\SpeciesVerificationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreSpeciesSourceRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'publisher' => ['required', 'string', 'max:255'],
            'source_type' => ['required', Rule::enum(KnowledgeSourceType::class)],
            'url' => ['nullable', 'string', 'max:2048'],
            'document_identifier' => ['nullable', 'string', 'max:191'],
            'language' => ['nullable', 'string', 'max:8'],
            'published_at' => ['nullable', 'date'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date'],
            'retrieved_at' => ['nullable', 'date', 'required_with:url'],
            'is_official' => ['sometimes', 'boolean'],
            'verification_status' => ['sometimes', Rule::enum(SpeciesVerificationStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'claim_key' => ['nullable', 'string', 'max:64'],
            'page_reference' => ['nullable', 'string', 'max:64'],
            'section_reference' => ['nullable', 'string', 'max:64'],
            'quotation_excerpt' => ['nullable', 'string', 'max:280'],
            'editor_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
