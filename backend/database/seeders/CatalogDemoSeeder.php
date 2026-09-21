<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Catalog\Enums\AttributeStatus;
use App\Domains\Catalog\Enums\AttributeType;
use App\Domains\Catalog\Enums\AttributeValueStatus;
use App\Domains\Catalog\Enums\CatalogStatus;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Enums\ProductVariantStatus;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeTranslation;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\AttributeValueTranslation;
use App\Domains\Catalog\Models\Brand;
use App\Domains\Catalog\Models\BrandTranslation;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\CategoryTranslation;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductTranslation;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Support\CatalogSlug;
use App\Domains\Catalog\Support\ColorHex;
use App\Domains\Catalog\Support\Variants\VariantCombination;
use Illuminate\Database\Seeder;

/**
 * Local/demo catalog seed only. No prices, barcodes, inventory, or media.
 */
final class CatalogDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $hunting = $this->category('hunting', 'ნადირობა', 'Hunting', 0);
        $this->category('fishing', 'თევზაობა', 'Fishing', 1);
        $this->category('camping', 'კემპინგი', 'Camping', 2);

        $brand = Brand::query()->whereHas('translations', fn ($q) => $q->where('slug', 'outdoor-demo'))->first();
        if ($brand === null) {
            $brand = Brand::query()->create([
                'status' => CatalogStatus::Active,
                'sort_order' => 0,
            ]);
            BrandTranslation::query()->create([
                'brand_id' => $brand->id,
                'locale' => 'ka',
                'name' => 'დემო ბრენდი',
                'slug' => 'demo-brendi',
            ]);
            BrandTranslation::query()->create([
                'brand_id' => $brand->id,
                'locale' => 'en',
                'name' => 'Demo Brand',
                'slug' => 'outdoor-demo',
            ]);
        }

        $attributes = $this->attributes();

        $optic = Product::query()->firstOrCreate(
            ['model_number' => 'DEMO-OPTIC-1'],
            [
                'brand_id' => $brand->id,
                'primary_category_id' => $hunting->id,
                'status' => ProductStatus::Draft,
                'is_featured' => true,
                'sort_order' => 0,
            ],
        );

        ProductTranslation::query()->updateOrCreate(
            ['product_id' => $optic->id, 'locale' => 'ka'],
            [
                'name' => 'დემო ოპტიკა',
                'slug' => CatalogSlug::normalize('demo-optika'),
                'short_description' => 'დემო მოკლე აღწერა',
                'description' => '<p>დემო აღწერა ნადირობის ოპტიკისთვის.</p>',
            ],
        );

        $optic->categories()->syncWithoutDetaching([
            $hunting->id => ['sort_order' => 0],
        ]);

        // Multi-axis demo: magnification x reticle.
        $optic->variantAttributes()->syncWithoutDetaching([
            $attributes['magnification']->id => ['sort_order' => 0],
            $attributes['reticle']->id => ['sort_order' => 1],
        ]);

        $magnifications = $this->valuesFor($attributes['magnification'], ['x3_9x40', 'x4_16x50']);
        $reticles = $this->valuesFor($attributes['reticle'], ['mil_dot', 'bdc']);

        $sequence = 1;
        foreach ($magnifications as $magnification) {
            foreach ($reticles as $reticle) {
                $this->variant(
                    $optic,
                    [$magnification, $reticle],
                    $sequence,
                    isDefault: $sequence === 1,
                );
                $sequence++;
            }
        }

        // Zero-axis demo: a single empty-combination default variant.
        $knife = Product::query()->firstOrCreate(
            ['model_number' => 'DEMO-KNIFE-1'],
            [
                'brand_id' => $brand->id,
                'primary_category_id' => $hunting->id,
                'status' => ProductStatus::Draft,
                'is_featured' => false,
                'sort_order' => 1,
            ],
        );

        ProductTranslation::query()->updateOrCreate(
            ['product_id' => $knife->id, 'locale' => 'ka'],
            [
                'name' => 'დემო დანა',
                'slug' => CatalogSlug::normalize('demo-dana'),
                'short_description' => 'დემო მოკლე აღწერა',
                'description' => '<p>დემო აღწერა სანადირო დანისთვის.</p>',
            ],
        );

        $knife->categories()->syncWithoutDetaching([
            $hunting->id => ['sort_order' => 0],
        ]);

        $this->variant($knife, [], 1, isDefault: true);
    }

    private function category(string $key, string $ka, string $en, int $sort): Category
    {
        $existing = Category::query()
            ->whereHas('translations', fn ($q) => $q->where('locale', 'en')->where('slug', $key))
            ->first();

        if ($existing !== null) {
            CategoryTranslation::query()->updateOrCreate(
                ['category_id' => $existing->id, 'locale' => 'ka'],
                ['name' => $ka, 'slug' => $key],
            );
            CategoryTranslation::query()->updateOrCreate(
                ['category_id' => $existing->id, 'locale' => 'en'],
                ['name' => $en, 'slug' => $key],
            );

            return $existing;
        }

        $category = Category::query()->create([
            'status' => CatalogStatus::Active,
            'sort_order' => $sort,
        ]);

        CategoryTranslation::query()->create([
            'category_id' => $category->id,
            'locale' => 'ka',
            'name' => $ka,
            'slug' => $key,
        ]);
        CategoryTranslation::query()->create([
            'category_id' => $category->id,
            'locale' => 'en',
            'name' => $en,
            'slug' => $key,
        ]);

        return $category;
    }

    /**
     * @return array<string, Attribute>
     */
    private function attributes(): array
    {
        $color = $this->attribute('color', AttributeType::Color, 'ფერი', 'Color', 0, true);
        $this->value($color, 'black', 'შავი', 'Black', 0, '#000000');
        $this->value($color, 'olive', 'ზეთისხილისფერი', 'Olive', 1, '#556B2F');
        $this->value($color, 'tan', 'ქვიშისფერი', 'Tan', 2, '#D2B48C');

        $size = $this->attribute('size', AttributeType::Select, 'ზომა', 'Size', 1, true);
        $this->value($size, 's', 'S', 'S', 0);
        $this->value($size, 'm', 'M', 'M', 1);
        $this->value($size, 'l', 'L', 'L', 2);

        $length = $this->attribute('length', AttributeType::Select, 'სიგრძე', 'Length', 2, true);
        $this->value($length, 'cm_100', '100 სმ', '100 cm', 0);
        $this->value($length, 'cm_120', '120 სმ', '120 cm', 1);

        $magnification = $this->attribute('magnification', AttributeType::Select, 'გადიდება', 'Magnification', 3, true);
        $this->value($magnification, 'x3_9x40', '3-9x40', '3-9x40', 0);
        $this->value($magnification, 'x4_16x50', '4-16x50', '4-16x50', 1);

        $reticle = $this->attribute('reticle', AttributeType::Select, 'ბადე', 'Reticle', 4, true);
        $this->value($reticle, 'mil_dot', 'მილ-დოტი', 'Mil-Dot', 0);
        $this->value($reticle, 'bdc', 'BDC', 'BDC', 1);

        return [
            'color' => $color,
            'size' => $size,
            'length' => $length,
            'magnification' => $magnification,
            'reticle' => $reticle,
        ];
    }

    private function attribute(
        string $code,
        AttributeType $type,
        string $ka,
        string $en,
        int $sort,
        bool $filterable,
    ): Attribute {
        $attribute = Attribute::query()->firstOrCreate(
            ['code' => $code],
            [
                'type' => $type,
                'status' => AttributeStatus::Active,
                'is_filterable' => $filterable,
                'sort_order' => $sort,
            ],
        );

        AttributeTranslation::query()->updateOrCreate(
            ['attribute_id' => $attribute->id, 'locale' => 'ka'],
            ['name' => $ka],
        );
        AttributeTranslation::query()->updateOrCreate(
            ['attribute_id' => $attribute->id, 'locale' => 'en'],
            ['name' => $en],
        );

        return $attribute;
    }

    private function value(
        Attribute $attribute,
        string $code,
        string $ka,
        string $en,
        int $sort,
        ?string $colorHex = null,
    ): AttributeValue {
        $value = AttributeValue::query()->firstOrCreate(
            ['attribute_id' => $attribute->id, 'code' => $code],
            [
                'status' => AttributeValueStatus::Active,
                'sort_order' => $sort,
                'color_hex' => ColorHex::normalize($colorHex),
            ],
        );

        AttributeValueTranslation::query()->updateOrCreate(
            ['attribute_value_id' => $value->id, 'locale' => 'ka'],
            ['name' => $ka],
        );
        AttributeValueTranslation::query()->updateOrCreate(
            ['attribute_value_id' => $value->id, 'locale' => 'en'],
            ['name' => $en],
        );

        return $value;
    }

    /**
     * @param  list<string>  $codes
     * @return list<AttributeValue>
     */
    private function valuesFor(Attribute $attribute, array $codes): array
    {
        return AttributeValue::query()
            ->where('attribute_id', $attribute->id)
            ->whereIn('code', $codes)
            ->orderBy('sort_order')
            ->get()
            ->values()
            ->all();
    }

    /**
     * @param  list<AttributeValue>  $values
     */
    private function variant(Product $product, array $values, int $sequence, bool $isDefault): ProductVariant
    {
        $combination = VariantCombination::fromPairs(array_map(
            static fn (AttributeValue $value): array => [
                'attribute_id' => (int) $value->attribute_id,
                'attribute_value_id' => (int) $value->id,
            ],
            $values,
        ));

        $sku = 'PRD-'.$product->id.'-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);

        $variant = ProductVariant::query()->firstOrCreate(
            ['product_id' => $product->id, 'combination_hash' => $combination->hash],
            [
                'sku' => $sku,
                'barcode' => null,
                'status' => ProductVariantStatus::Active,
                'is_default' => $isDefault,
                'sort_order' => $sequence - 1,
                'combination_signature' => $combination->signature,
            ],
        );

        foreach ($combination->pairs as $pair) {
            $variant->combinationRows()->firstOrCreate([
                'attribute_id' => $pair['attribute_id'],
                'attribute_value_id' => $pair['attribute_value_id'],
            ]);
        }

        return $variant;
    }
}
