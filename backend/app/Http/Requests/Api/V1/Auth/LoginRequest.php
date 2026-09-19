<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use App\Domains\Identity\DTOs\LoginCustomerData;
use App\Domains\Identity\Support\EmailNormalizer;
use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
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
            'email' => ['required', 'string', 'email:filter', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    public function toData(): LoginCustomerData
    {
        /** @var array{email: string, password: string, remember?: bool} $validated */
        $validated = $this->validated();

        return new LoginCustomerData(
            email: $validated['email'],
            password: $validated['password'],
            remember: (bool) ($validated['remember'] ?? false),
        );
    }
}
