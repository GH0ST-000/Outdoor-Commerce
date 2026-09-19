<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Services\Variants;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Support\Variants\Barcode;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Optional GTIN barcodes. Blank input clears the barcode.
 */
final class BarcodeService
{
    /**
     * @throws ValidationException
     */
    public function fromInput(?string $raw, string $field = 'barcode'): ?Barcode
    {
        try {
            return Barcode::fromNullable($raw);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([$field => [$e->getMessage()]]);
        }
    }

    /**
     * @throws ValidationException
     */
    public function assertUnique(?Barcode $barcode, ?int $ignoreVariantId = null, string $field = 'barcode'): void
    {
        if ($barcode === null) {
            return;
        }

        $taken = ProductVariant::withTrashed()
            ->where('barcode', $barcode->value)
            ->when($ignoreVariantId !== null, fn ($query) => $query->whereKeyNot($ignoreVariantId))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([$field => ['This barcode is already in use.']]);
        }
    }
}
