<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Checkout;

use App\Domains\Checkout\DTOs\CheckoutAddressWriteData;
use App\Domains\Checkout\Support\CheckoutPhoneNormalizer;
use App\Domains\Checkout\Support\CheckoutTextNormalizer;
use App\Http\Support\RequiresCheckoutIdempotencyKey;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateCheckoutAddressRequest extends FormRequest
{
    use RequiresCheckoutIdempotencyKey;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'recipient_first_name' => CheckoutTextNormalizer::name($this->input('recipient_first_name')),
            'recipient_last_name' => CheckoutTextNormalizer::name($this->input('recipient_last_name')),
            'phone' => CheckoutPhoneNormalizer::normalize($this->input('phone')),
            'country_code' => strtoupper(CheckoutTextNormalizer::line($this->input('country_code', (string) config('checkout.default_country', 'GE')))),
            'region' => CheckoutTextNormalizer::line($this->input('region')),
            'municipality_or_city' => CheckoutTextNormalizer::line($this->input('municipality_or_city')),
            'district' => CheckoutTextNormalizer::line($this->input('district')),
            'street' => CheckoutTextNormalizer::line($this->input('street')),
            'house_number' => CheckoutTextNormalizer::line($this->input('house_number')),
            'apartment' => CheckoutTextNormalizer::line($this->input('apartment')),
            'entrance' => CheckoutTextNormalizer::line($this->input('entrance')),
            'floor' => CheckoutTextNormalizer::line($this->input('floor')),
            'postal_code' => CheckoutTextNormalizer::line($this->input('postal_code')),
            'landmark' => CheckoutTextNormalizer::line($this->input('landmark')),
            'delivery_instructions' => CheckoutTextNormalizer::line($this->input('delivery_instructions')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recipient_first_name' => ['required', 'string', 'max:80', $this->safeText()],
            'recipient_last_name' => ['required', 'string', 'max:80', $this->safeText()],
            'phone' => ['required', 'string', 'max:32', 'regex:/^\+[1-9]\d{7,14}$/'],
            'country_code' => ['required', 'string', 'size:2'],
            'region' => ['nullable', 'string', 'max:128', $this->safeText()],
            'municipality_or_city' => ['required', 'string', 'max:128', $this->safeText()],
            'district' => ['nullable', 'string', 'max:128', $this->safeText()],
            'street' => ['nullable', 'string', 'max:160', $this->safeText()],
            'house_number' => ['nullable', 'string', 'max:32', $this->safeText()],
            'apartment' => ['nullable', 'string', 'max:32', $this->safeText()],
            'entrance' => ['nullable', 'string', 'max:32', $this->safeText()],
            'floor' => ['nullable', 'string', 'max:16', $this->safeText()],
            'postal_code' => ['nullable', 'string', 'max:16', $this->safeText()],
            'landmark' => ['nullable', 'string', 'max:160', $this->safeText()],
            'delivery_instructions' => ['nullable', 'string', 'max:500', $this->safeText()],
            'billing_same_as_shipping' => ['sometimes', 'boolean'],
            'save_to_account' => ['sometimes', 'boolean'],
            'checkout_version' => ['sometimes', 'integer', 'min:0'],
            'latitude' => ['prohibited'],
            'longitude' => ['prohibited'],
            'delivery_total_minor' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $ka = $this->locale() === 'ka';

        return [
            'municipality_or_city.required' => $ka ? 'ქალაქი ან მუნიციპალიტეტი აუცილებელია.' : 'City or municipality is required.',
            'phone.regex' => $ka ? 'ტელეფონის ნომერი არასწორია.' : 'Enter a valid international phone number.',
            'country_code.size' => $ka ? 'ქვეყნის კოდი უნდა იყოს ISO ფორმატში.' : 'Country must be an ISO two-letter code.',
        ];
    }

    public function checkoutVersion(): ?int
    {
        return $this->exists('checkout_version') ? (int) $this->input('checkout_version') : null;
    }

    public function addressData(): CheckoutAddressWriteData
    {
        $nullable = static fn (mixed $value): ?string => is_string($value) && $value !== '' ? $value : null;

        return new CheckoutAddressWriteData(
            recipientFirstName: (string) $this->validated('recipient_first_name'),
            recipientLastName: (string) $this->validated('recipient_last_name'),
            phone: (string) $this->validated('phone'),
            countryCode: (string) $this->validated('country_code'),
            region: $nullable($this->validated('region') ?? null),
            municipalityOrCity: $nullable($this->validated('municipality_or_city') ?? null),
            district: $nullable($this->validated('district') ?? null),
            street: $nullable($this->validated('street') ?? null),
            houseNumber: $nullable($this->validated('house_number') ?? null),
            apartment: $nullable($this->validated('apartment') ?? null),
            entrance: $nullable($this->validated('entrance') ?? null),
            floor: $nullable($this->validated('floor') ?? null),
            postalCode: $nullable($this->validated('postal_code') ?? null),
            landmark: $nullable($this->validated('landmark') ?? null),
            deliveryInstructions: $nullable($this->validated('delivery_instructions') ?? null),
            billingSameAsShipping: (bool) ($this->validated('billing_same_as_shipping') ?? true),
            saveToAccount: (bool) ($this->validated('save_to_account') ?? false),
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
