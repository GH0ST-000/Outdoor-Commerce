<?php

declare(strict_types=1);

namespace App\Domains\Catalog\PublicApi\Services;

use App\Domains\Catalog\Enums\AttributeType;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\PublicApi\Data\PublicCatalogContextData;
use App\Domains\Inventory\DTOs\PublicAvailabilityData;
use App\Domains\Pricing\DTOs\PublicPriceQuoteData;

final class PublicVariantMatrixBuilder
{
    public function __construct(
        private readonly PublicAvailabilityPresenter $availability,
        private readonly PublicPricePresenter $prices,
        private readonly PublicMediaPresenter $media,
    ) {}

    /**
     * @param  list<ProductVariant>  $publicVariants
     * @param  array<int, PublicPriceQuoteData>  $quotes
     * @param  array<int, PublicAvailabilityData>  $availability
     * @return array<string, mixed>
     */
    public function build(
        Product $product,
        array $publicVariants,
        array $quotes,
        array $availability,
        PublicCatalogContextData $context,
        ?int $defaultVariantId,
    ): array {
        $product->loadMissing(['variantAttributes.translations', 'variantAttributes.values.translations']);

        $axes = [];
        foreach ($product->variantAttributes as $attribute) {
            $values = [];
            foreach ($attribute->values->sortBy([['sort_order', 'asc'], ['id', 'asc']]) as $value) {
                $used = false;
                foreach ($publicVariants as $variant) {
                    if ($variant->combinationRows->contains(fn ($row): bool => (int) $row->attribute_value_id === (int) $value->id)) {
                        $used = true;
                        break;
                    }
                }
                if (! $used) {
                    continue;
                }
                $values[] = [
                    'id' => $value->id,
                    'code' => $value->code,
                    'name' => $value->localizedName($context->locale) ?? $value->code,
                    'color_hex' => $value->color_hex,
                ];
            }

            $axes[] = [
                'id' => $attribute->id,
                'code' => $attribute->code,
                'name' => $attribute->localizedName($context->locale) ?? $attribute->code,
                'type' => $attribute->type === AttributeType::Color ? 'color' : 'select',
                'values' => $values,
            ];
        }

        $combinations = [];
        foreach ($publicVariants as $variant) {
            $quote = $quotes[$variant->id] ?? null;
            $stock = $availability[$variant->id] ?? new PublicAvailabilityData($variant->id, 0, false);
            $pairs = [];
            $labelParts = [];
            foreach ($product->variantAttributes as $attribute) {
                $row = $variant->combinationRows->firstWhere('attribute_id', $attribute->id);
                $value = $row?->attributeValue;
                if ($value === null) {
                    continue;
                }
                $name = $value->localizedName($context->locale) ?? $value->code;
                $labelParts[] = $name;
                $pairs[] = [
                    'code' => $attribute->code,
                    'name' => $attribute->localizedName($context->locale) ?? $attribute->code,
                    'value' => [
                        'code' => $value->code,
                        'name' => $name,
                        'color_hex' => $value->color_hex,
                    ],
                ];
            }

            $combinations[] = [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'is_default' => $defaultVariantId === (int) $variant->id,
                'combination_label' => implode(' / ', $labelParts),
                'attributes' => $pairs,
                'media' => $this->media->galleryForVariant($variant, $product, $context->locale),
                'price' => $quote !== null ? $this->prices->variant($quote) : $this->prices->missing($context->currency, $context->effectiveAt),
                'availability' => $this->availability->present($stock, $quote !== null),
            ];
        }

        return [
            'axes' => $axes,
            'combinations' => $combinations,
            'default_variant_id' => $defaultVariantId,
        ];
    }
}
