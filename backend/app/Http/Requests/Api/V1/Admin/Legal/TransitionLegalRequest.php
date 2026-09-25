<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Legal;

use Illuminate\Foundation\Http\FormRequest;

final class TransitionLegalRequest extends FormRequest
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
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
