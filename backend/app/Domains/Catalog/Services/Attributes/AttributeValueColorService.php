<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Services\Attributes;

use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Support\ColorHex;
use Illuminate\Validation\ValidationException;

/**
 * `color_hex` belongs to color attributes only and is stored as #RRGGBB uppercase.
 */
final class AttributeValueColorService
{
    public function resolve(Attribute $attribute, ?string $raw, bool $provided): ?string
    {
        if (! $provided) {
            return null;
        }

        if ($raw === null || trim($raw) === '') {
            return null;
        }

        if (! $attribute->type->allowsColorHex()) {
            throw ValidationException::withMessages([
                'color_hex' => ['Only color attributes accept a color value.'],
            ]);
        }

        $normalized = ColorHex::normalize($raw);
        if ($normalized === null) {
            throw ValidationException::withMessages([
                'color_hex' => ['Use a 3- or 6-digit hex color such as #1A2B3C.'],
            ]);
        }

        return $normalized;
    }
}
