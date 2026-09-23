<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Checkout;

use App\Domains\Checkout\DTOs\CheckoutContactData;
use App\Domains\Checkout\Support\CheckoutPhoneNormalizer;
use App\Domains\Checkout\Support\CheckoutTextNormalizer;
use App\Http\Support\RequiresCheckoutIdempotencyKey;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateCheckoutContactRequest extends FormRequest
{
    use RequiresCheckoutIdempotencyKey;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' => CheckoutTextNormalizer::name($this->input('first_name')),
            'last_name' => CheckoutTextNormalizer::name($this->input('last_name')),
            'email' => CheckoutTextNormalizer::email($this->input('email')),
            'phone' => CheckoutPhoneNormalizer::normalize($this->input('phone')),
            'customer_note' => CheckoutTextNormalizer::line($this->input('customer_note')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:80', $this->safeText()],
            'last_name' => ['required', 'string', 'max:80', $this->safeText()],
            'email' => ['required', 'email:filter', 'max:255'],
            'phone' => ['required', 'string', 'max:32', 'regex:/^\+[1-9]\d{7,14}$/'],
            'customer_note' => ['nullable', 'string', 'max:1000', $this->safeText()],
            'checkout_version' => ['sometimes', 'integer', 'min:0'],
            'user_id' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $ka = $this->locale() === 'ka';

        return [
            'first_name.required' => $ka ? 'სახელი აუცილებელია.' : 'First name is required.',
            'last_name.required' => $ka ? 'გვარი აუცილებელია.' : 'Last name is required.',
            'email.required' => $ka ? 'ელფოსტა აუცილებელია.' : 'Email is required.',
            'email.email' => $ka ? 'ელფოსტის ფორმატი არასწორია.' : 'Enter a valid email address.',
            'phone.required' => $ka ? 'ტელეფონი აუცილებელია.' : 'Phone is required.',
            'phone.regex' => $ka ? 'ტელეფონის ნომერი არასწორია.' : 'Enter a valid international phone number.',
        ];
    }

    public function checkoutVersion(): ?int
    {
        return $this->exists('checkout_version') ? (int) $this->input('checkout_version') : null;
    }

    public function contactData(): CheckoutContactData
    {
        $note = $this->validated('customer_note') ?? null;

        return new CheckoutContactData(
            firstName: (string) $this->validated('first_name'),
            lastName: (string) $this->validated('last_name'),
            email: (string) $this->validated('email'),
            phone: (string) $this->validated('phone'),
            customerNote: is_string($note) && $note !== '' ? $note : null,
        );
    }

    private function locale(): string
    {
        $header = strtolower((string) $this->header('X-Locale', $this->header('Accept-Language', 'ka')));

        return str_starts_with($header, 'en') ? 'en' : 'ka';
    }

    private function safeText(): \Closure
    {
        return static function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_string($value)) {
                return;
            }
            if (CheckoutTextNormalizer::containsMarkup($value) || CheckoutTextNormalizer::containsControlCharacters($value)) {
                $fail('The '.$attribute.' contains unsupported characters.');
            }
        };
    }
}
