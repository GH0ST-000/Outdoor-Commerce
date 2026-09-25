<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Legal;

use App\Domains\Legal\Enums\ProvisionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreLegalProvisionRequest extends FormRequest
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
            'parent_provision_id' => ['nullable', 'uuid'],
            'provision_type' => [$required, Rule::enum(ProvisionType::class)],
            'reference_code' => [$required, 'string', 'max:128'],
            'heading' => ['nullable', 'string', 'max:255'],
            'official_text' => [$required, 'string'],
            'normalized_summary' => ['nullable', 'string', 'max:4000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date'],
            'review_status' => ['sometimes', 'string', 'max:32'],
        ];
    }
}
