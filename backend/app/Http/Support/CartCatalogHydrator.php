<?php

declare(strict_types=1);

namespace App\Http\Support;

use App\Domains\Cart\DTOs\CartLineSnapshotData;
use App\Domains\Cart\DTOs\CartSnapshotData;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\PublicApi\Services\PublicMediaPresenter;

final class CartCatalogHydrator
{
    public function __construct(
        private readonly PublicMediaPresenter $media,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(CartSnapshotData $cart, string $locale): array
    {
        $payload = $cart->toArray();
        $productIds = array_values(array_unique(array_map(
            static fn (CartLineSnapshotData $line): int => $line->productId,
            $cart->items,
        )));
        $variantIds = array_values(array_unique(array_map(
            static fn (CartLineSnapshotData $line): int => $line->variantId,
            $cart->items,
        )));

        $products = Product::query()
            ->with(['translations', 'brand.translations', 'readyMediaAttachments.asset.derivatives', 'readyMediaAttachments.translations'])
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');
        $variants = ProductVariant::query()
            ->with(['attributeValues.translations', 'attributeValues.attribute.translations'])
            ->whereIn('id', $variantIds)
            ->get()
            ->keyBy('id');

        $payload['items'] = array_map(function (CartLineSnapshotData $line) use ($products, $variants, $locale): array {
            $row = $line->toArray();
            $product = $products->get($line->productId);
            $variant = $variants->get($line->variantId);
            $slug = $product?->translation($locale)?->slug;
            $media = null;
            if ($product instanceof Product) {
                $media = $this->media->primaryForProduct($product, $locale);
            }

            $row['product'] = [
                'id' => $line->productId,
                'slug' => $slug,
                'name' => $line->productName,
                'href' => is_string($slug) && $slug !== '' ? '/products/'.$slug : null,
                'brand' => [
                    'name' => $product?->brand?->localizedName($locale),
                ],
                'primary_media' => $media,
            ];
            $row['variant'] = [
                'id' => $line->variantId,
                'sku' => $line->sku,
                'label' => $line->variantLabel,
                'attributes' => $this->attributes($variant, $locale),
            ];
            unset($row['product_id'], $row['variant_id'], $row['product_name'], $row['variant_label'], $row['sku']);

            return $row;
        }, $cart->items);

        return $payload;
    }

    /**
     * @return list<array{code: string, name: string, value: array{code: string, name: string, color_hex: string|null}}>
     */
    private function attributes(?ProductVariant $variant, string $locale): array
    {
        if ($variant === null) {
            return [];
        }

        $attributes = [];
        foreach ($variant->attributeValues as $value) {
            $attribute = $value->attribute;
            $attributes[] = [
                'code' => (string) $attribute?->code,
                'name' => (string) ($attribute?->localizedName($locale) ?? $attribute?->code),
                'value' => [
                    'code' => (string) $value->code,
                    'name' => (string) ($value->localizedName($locale) ?? $value->code),
                    'color_hex' => $value->color_hex ?? null,
                ],
            ];
        }

        return $attributes;
    }
}
