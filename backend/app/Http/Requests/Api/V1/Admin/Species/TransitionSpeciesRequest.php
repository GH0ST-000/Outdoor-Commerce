<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Species;

use Illuminate\Foundation\Http\FormRequest;

final class TransitionSpeciesRequest extends FormRequest
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
        $needsReason = $this->is('*/unpublish') || $this->is('*/archive');

        return [
            'reason' => [$needsReason ? 'required' : 'nullable', 'string', 'max:240'],
            'content_version' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
