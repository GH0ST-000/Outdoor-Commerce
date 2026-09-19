<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Services\Attributes;

use App\Domains\Catalog\DTOs\Attributes\AttributeTranslationData;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeTranslation;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\AttributeValueTranslation;
use App\Domains\Catalog\Support\CatalogLocales;
use Illuminate\Validation\ValidationException;

/**
 * Writes attribute / attribute value translations and enforces the Georgian
 * baseline required before anything can be published.
 */
final class AttributeTranslationSynchronizer
{
    /**
     * @param  list<AttributeTranslationData>  $translations
     */
    public function syncAttribute(Attribute $attribute, array $translations): void
    {
        foreach ($this->normalize($translations) as $translation) {
            AttributeTranslation::query()->updateOrCreate(
                ['attribute_id' => $attribute->id, 'locale' => $translation->locale],
                ['name' => trim($translation->name), 'description' => $translation->description],
            );
        }

        $attribute->unsetRelation('translations');
    }

    /**
     * @param  list<AttributeTranslationData>  $translations
     */
    public function syncValue(AttributeValue $value, array $translations): void
    {
        foreach ($this->normalize($translations) as $translation) {
            AttributeValueTranslation::query()->updateOrCreate(
                ['attribute_value_id' => $value->id, 'locale' => $translation->locale],
                ['name' => trim($translation->name), 'description' => $translation->description],
            );
        }

        $value->unsetRelation('translations');
    }

    public function assertGeorgianPresent(Attribute $attribute): void
    {
        $exists = $attribute->translations()
            ->where('locale', CatalogLocales::default())
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'translations' => ['A Georgian translation is required before activating this attribute.'],
            ]);
        }
    }

    public function assertGeorgianPresentForValue(AttributeValue $value): void
    {
        $exists = $value->translations()
            ->where('locale', CatalogLocales::default())
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'translations' => ['A Georgian translation is required before activating this value.'],
            ]);
        }
    }

    /**
     * @param  list<AttributeTranslationData>  $translations
     * @return list<AttributeTranslationData>
     */
    private function normalize(array $translations): array
    {
        $seen = [];

        foreach ($translations as $index => $translation) {
            if (! CatalogLocales::isSupported($translation->locale)) {
                throw ValidationException::withMessages([
                    "translations.{$index}.locale" => ['Unsupported locale.'],
                ]);
            }

            if (isset($seen[$translation->locale])) {
                throw ValidationException::withMessages([
                    'translations' => ['Duplicate locale entries are not allowed.'],
                ]);
            }

            if (trim($translation->name) === '') {
                throw ValidationException::withMessages([
                    "translations.{$index}.name" => ['A name is required.'],
                ]);
            }

            $seen[$translation->locale] = true;
        }

        return $translations;
    }
}
