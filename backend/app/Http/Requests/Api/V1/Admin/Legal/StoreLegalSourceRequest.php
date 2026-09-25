<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Legal;

use App\Domains\Legal\Enums\LegalSourceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreLegalSourceRequest extends FormRequest
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
            'authority_id' => [$required, 'uuid'],
            'name' => [$required, 'string', 'max:191'],
            'slug' => ['nullable', 'string', 'max:191'],
            'source_type' => [$required, Rule::enum(LegalSourceType::class)],
            'official_base_url' => ['nullable', 'url:https', 'max:2048'],
            'allowed_domain' => ['nullable', 'string', 'max:191'],
            'language_code' => ['nullable', 'string', 'max:8'],
            'jurisdiction_code' => ['nullable', Rule::in(config('legal.jurisdictions', ['GE']))],
            'trust_level' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'monitor_for_changes' => ['sometimes', 'boolean'],
        ];
    }
}
