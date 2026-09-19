<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Services\Variants;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Support\Variants\Sku;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * SKU normalization, uniqueness and sequential generation.
 *
 * Generated SKUs look like `PRD-{productId}-{seq}` with a 3-digit sequence.
 * Callers must hold the product row lock so concurrent generations serialize;
 * the bounded retry loop only skips sequences already taken by manual SKUs.
 */
final class SkuService
{
    private const MAX_ATTEMPTS = 1000;

    public function normalize(string $raw): string
    {
        return Sku::normalize($raw);
    }

    /**
     * @throws ValidationException
     */
    public function fromInput(string $raw, string $field = 'sku'): Sku
    {
        try {
            return Sku::fromString($raw);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([$field => [$e->getMessage()]]);
        }
    }

    /**
     * @throws ValidationException
     */
    public function assertUnique(Sku $sku, ?int $ignoreVariantId = null, string $field = 'sku'): void
    {
        $taken = ProductVariant::withTrashed()
            ->where('sku', $sku->value)
            ->when($ignoreVariantId !== null, fn ($query) => $query->whereKeyNot($ignoreVariantId))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([$field => ['This SKU is already in use.']]);
        }
    }

    public function generate(Product $product): Sku
    {
        return $this->generateMany($product, 1)[0];
    }

    /**
     * Allocates a batch of unused sequential SKUs for a product.
     *
     * @return non-empty-list<Sku>
     */
    public function generateMany(Product $product, int $count): array
    {
        $count = max(1, $count);
        $prefix = $this->prefixFor($product);
        $sequence = $this->nextSequence($prefix);
        $allocated = [];
        $attempts = 0;

        while (count($allocated) < $count) {
            if (++$attempts > self::MAX_ATTEMPTS) {
                throw ValidationException::withMessages([
                    'sku' => ['Unable to allocate a free generated SKU. Provide SKUs manually.'],
                ]);
            }

            $candidate = $prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
            $sequence++;

            if (ProductVariant::withTrashed()->where('sku', $candidate)->exists()) {
                continue;
            }

            $allocated[$candidate] = Sku::fromString($candidate);
        }

        /** @var non-empty-list<Sku> $values */
        $values = array_values($allocated);

        return $values;
    }

    private function prefixFor(Product $product): string
    {
        $configured = (string) config('catalog.variants.sku.prefix', 'PRD');
        $prefix = Sku::normalize($configured);

        return ($prefix === '' ? 'PRD' : $prefix).'-'.$product->id.'-';
    }

    private function nextSequence(string $prefix): int
    {
        $existing = ProductVariant::withTrashed()
            ->where('sku', 'like', addcslashes($prefix, '%_\\').'%')
            ->pluck('sku');

        $highest = 0;
        foreach ($existing as $sku) {
            $suffix = substr((string) $sku, strlen($prefix));
            if ($suffix !== '' && ctype_digit($suffix)) {
                $highest = max($highest, (int) $suffix);
            }
        }

        return $highest + 1;
    }
}
