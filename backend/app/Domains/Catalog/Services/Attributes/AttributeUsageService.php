<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Services\Attributes;

use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\ProductVariantAttributeValue;

/**
 * Answers whether an attribute or value is load-bearing, which freezes its
 * identity fields (code, type).
 */
final class AttributeUsageService
{
    public function attributeIsInUse(Attribute $attribute): bool
    {
        if ($attribute->values()->withTrashed()->exists()) {
            return true;
        }

        if ($attribute->products()->exists()) {
            return true;
        }

        return ProductVariantAttributeValue::query()
            ->where('attribute_id', $attribute->id)
            ->exists();
    }

    public function valueIsInUse(AttributeValue $value): bool
    {
        return ProductVariantAttributeValue::query()
            ->where('attribute_value_id', $value->id)
            ->exists();
    }
}
