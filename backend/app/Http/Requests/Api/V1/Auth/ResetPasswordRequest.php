<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use App\Domains\Identity\Support\EmailNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => EmailNormalizer::normalize((string) $this->input('email', '')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email:filter', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
