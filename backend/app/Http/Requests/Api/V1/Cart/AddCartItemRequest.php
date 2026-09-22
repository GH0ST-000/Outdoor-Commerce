<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Cart;

use App\Http\Support\RequiresCartIdempotencyKey;
use Illuminate\Foundation\Http\FormRequest;

final class AddCartItemRequest extends FormRequest
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
            'variant_id' => ['required', 'integer', 'min:1'],
            'product_id' => ['sometimes', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.max(1, (int) config('cart.max_line_quantity', 12))],
            'cart_version' => ['sometimes', 'integer', 'min:0'],
            'unit_price_minor' => ['prohibited'],
            'price' => ['prohibited'],
            'total' => ['prohibited'],
        ];
    }

    public function quantity(): int
    {
        return (int) $this->validated('quantity');
    }

    public function variantId(): int
    {
        return (int) $this->validated('variant_id');
    }

    public function productId(): ?int
    {
        $value = $this->validated('product_id') ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    public function cartVersion(): ?int
    {
        if (! $this->exists('cart_version')) {
            return null;
        }

        return (int) $this->input('cart_version');
    }
}
