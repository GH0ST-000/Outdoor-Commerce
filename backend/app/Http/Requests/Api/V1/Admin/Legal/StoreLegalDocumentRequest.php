<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Legal;

use App\Domains\Legal\Enums\LegalDocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreLegalDocumentRequest extends FormRequest
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
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'source_id' => [$required, 'uuid'],
            'authority_id' => ['nullable', 'uuid'],
            'title' => [$required, 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:191'],
            'official_identifier' => ['nullable', 'string', 'max:191'],
            'document_type' => [$required, Rule::enum(LegalDocumentType::class)],
            'jurisdiction_code' => ['nullable', Rule::in(config('legal.jurisdictions', ['GE']))],
            'language_code' => ['nullable', 'string', 'max:8'],
            'official_url' => ['nullable', 'url', 'max:2048'],
            'publication_date' => ['nullable', 'date'],
            'original_effective_date' => ['nullable', 'date'],
            'status' => ['sometimes', 'string', 'max:32'],
        ];
    }
}
