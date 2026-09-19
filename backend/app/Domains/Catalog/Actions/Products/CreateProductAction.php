<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Actions\Products;

use App\Domains\Catalog\DTOs\Products\ProductTranslationData;
use App\Domains\Catalog\DTOs\Products\ProductWriteData;
use App\Domains\Catalog\Enums\CatalogStatus;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\Brand;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductTranslation;
use App\Domains\Catalog\Services\CatalogCache;
use App\Domains\Catalog\Services\Products\ProductCategorySynchronizer;
use App\Domains\Catalog\Services\Products\ProductReadinessService;
use App\Domains\Catalog\Support\CatalogLocales;
use App\Domains\Catalog\Support\CatalogSlug;
use App\Domains\Catalog\Support\HtmlContentSanitizer;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateProductAction
{
    public function __construct(
        private readonly ProductCategorySynchronizer $categorySynchronizer,
        private readonly ProductReadinessService $readinessService,
        private readonly HtmlContentSanitizer $sanitizer,
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly CatalogCache $catalogCache,
    ) {}

    public function execute(
        ProductWriteData $data,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): Product {
        $this->assertTranslations($data->translations, requireGeorgian: true);
        $this->assertBrand($data->brandId, $data->status);

        $product = DB::transaction(function () use ($data, $actor, $requestId, $ipAddress, $userAgent): Product {
            if ($data->status === ProductStatus::Active) {
                // Persist draft first structure then validate readiness after relations.
            }

            $product = new Product;
            $product->brand_id = $data->brandId;
            $product->status = $data->status === ProductStatus::Active
                ? ProductStatus::Draft
                : $data->status;
            $product->model_number = $data->modelNumber;
            $product->manufacturer_part_number = $data->manufacturerPartNumber;
            $product->is_featured = $data->isFeatured;
            $product->sort_order = max(0, $data->sortOrder);
            $product->created_by = (int) $actor->getAuthIdentifier();
            $product->updated_by = (int) $actor->getAuthIdentifier();
            $product->save();

            $this->persistTranslations($product, $data->translations);

            $categoryIds = $data->categoryIds ?? [];
            $this->categorySynchronizer->sync(
                $product,
                $data->primaryCategoryId,
                $categoryIds,
                $data->status === ProductStatus::Active ? ProductStatus::Draft : $data->status,
            );

            $product->refresh()->load(['translations', 'categories', 'brand', 'primaryCategory']);

            if ($data->status === ProductStatus::Active) {
                $this->readinessService->assertReadyForActivation($product);
                $product->status = ProductStatus::Active;
                $product->published_at = now();
                $product->save();
            }

            $this->recordAuditEvent->execute(new AuditEventData(
                event: AuditEvent::ProductCreated,
                actorUserId: (int) $actor->getAuthIdentifier(),
                subjectType: 'product',
                subjectId: (string) $product->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                newValues: [
                    'status' => $product->status->value,
                    'brand_id' => $product->brand_id,
                    'primary_category_id' => $product->primary_category_id,
                    'category_ids' => $product->categories->pluck('id')->all(),
                    'locales' => $product->translations->pluck('locale')->all(),
                    'is_featured' => $product->is_featured,
                ],
            ));

            return $product->fresh([
                'translations',
                'categories.translations',
                'brand.translations',
                'primaryCategory.translations',
            ]) ?? $product;
        });

        $this->catalogCache->bump();

        return $product;
    }

    /**
     * @param  list<ProductTranslationData>  $translations
     */
    private function assertTranslations(array $translations, bool $requireGeorgian): void
    {
        if ($translations === []) {
            throw ValidationException::withMessages([
                'translations' => ['At least one translation is required.'],
            ]);
        }

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
            $seen[$translation->locale] = true;

            $slug = CatalogSlug::normalize($translation->slug);
            if ($slug === '' || ! CatalogSlug::isValid($slug)) {
                throw ValidationException::withMessages([
                    "translations.{$index}.slug" => ['A valid slug is required.'],
                ]);
            }

            $exists = ProductTranslation::query()
                ->where('locale', $translation->locale)
                ->where('slug', $slug)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    "translations.{$index}.slug" => ['This slug is already taken for the locale.'],
                ]);
            }
        }

        if ($requireGeorgian && ! isset($seen[CatalogLocales::default()])) {
            throw ValidationException::withMessages([
                'translations' => ['A Georgian translation is required.'],
            ]);
        }
    }

    private function assertBrand(?int $brandId, ProductStatus $status): void
    {
        if ($brandId === null) {
            return;
        }

        $brand = Brand::query()->find($brandId);
        if ($brand === null) {
            throw ValidationException::withMessages([
                'brand_id' => ['The selected brand is invalid.'],
            ]);
        }

        if ($brand->status === CatalogStatus::Archived) {
            throw ValidationException::withMessages([
                'brand_id' => ['Archived brands cannot be assigned.'],
            ]);
        }

        if ($status === ProductStatus::Active && $brand->status !== CatalogStatus::Active) {
            throw ValidationException::withMessages([
                'brand_id' => ['An active brand is required for activation.'],
            ]);
        }
    }

    /**
     * @param  list<ProductTranslationData>  $translations
     */
    private function persistTranslations(Product $product, array $translations): void
    {
        foreach ($translations as $translation) {
            $product->translations()->create([
                'locale' => $translation->locale,
                'name' => trim($translation->name),
                'slug' => CatalogSlug::normalize($translation->slug),
                'short_description' => $translation->shortDescription,
                'description' => $this->sanitizer->sanitize($translation->description),
                'seo_title' => $translation->seoTitle,
                'seo_description' => $translation->seoDescription,
            ]);
        }
    }
}
