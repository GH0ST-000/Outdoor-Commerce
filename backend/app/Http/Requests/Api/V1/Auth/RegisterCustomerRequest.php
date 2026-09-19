<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use App\Domains\Identity\DTOs\RegisterCustomerData;
use App\Domains\Identity\Support\EmailNormalizer;
use App\Domains\Identity\Support\NameNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class RegisterCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' => NameNormalizer::normalize((string) $this->input('first_name', '')),
            'last_name' => NameNormalizer::normalize((string) $this->input('last_name', '')),
            'email' => EmailNormalizer::normalize((string) $this->input('email', '')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email:filter', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    public function toData(): RegisterCustomerData
    {
        /** @var array{first_name: string, last_name: string, email: string, password: string} $validated */
        $validated = $this->validated();

        return new RegisterCustomerData(
            firstName: $validated['first_name'],
            lastName: $validated['last_name'],
            email: $validated['email'],
            password: $validated['password'],
        );
    }
}
