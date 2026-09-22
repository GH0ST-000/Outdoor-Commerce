<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Cart;

use App\Http\Support\RequiresCartIdempotencyKey;
use Illuminate\Foundation\Http\FormRequest;

final class CartMutationRequest extends FormRequest
{
    use RequiresCartIdempotencyKey;

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
            'cart_version' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function cartVersion(): ?int
    {
        if (! $this->exists('cart_version')) {
            return null;
        }

        return (int) $this->input('cart_version');
    }
}
