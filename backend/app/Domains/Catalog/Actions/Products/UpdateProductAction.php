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

final class UpdateProductAction
{
    public function __construct(
        private readonly ProductCategorySynchronizer $categorySynchronizer,
        private readonly ProductReadinessService $readinessService,
        private readonly HtmlContentSanitizer $sanitizer,
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly CatalogCache $catalogCache,
    ) {}

    public function execute(
        Product $product,
        ProductWriteData $data,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): Product {
        $updated = DB::transaction(function () use (
            $product,
            $data,
            $actor,
            $requestId,
            $ipAddress,
            $userAgent,
        ): Product {
            /** @var Product $locked */
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $old = [
                'brand_id' => $locked->brand_id,
                'primary_category_id' => $locked->primary_category_id,
                'status' => $locked->status->value,
                'is_featured' => $locked->is_featured,
                'sort_order' => $locked->sort_order,
                'model_number' => $locked->model_number,
                'manufacturer_part_number' => $locked->manufacturer_part_number,
            ];

            $this->assertBrand($data->brandId, $data->status);

            $locked->brand_id = $data->brandId;
            $locked->model_number = $data->modelNumber;
            $locked->manufacturer_part_number = $data->manufacturerPartNumber;
            $locked->is_featured = $data->isFeatured;
            $locked->sort_order = max(0, $data->sortOrder);
            $locked->updated_by = (int) $actor->getAuthIdentifier();
            $locked->save();

            if ($data->syncTranslations) {
                $this->syncTranslations($locked, $data->translations, $data->removeEnglish);
            }

            if ($data->categoryIds !== null) {
                $this->categorySynchronizer->sync(
                    $locked,
                    $data->primaryCategoryId,
                    $data->categoryIds,
                    $data->status,
                );
            } elseif ($data->primaryCategoryId !== null) {
                $ids = $locked->categories()->pluck('categories.id')->map(fn ($id) => (int) $id)->all();
                if (! in_array($data->primaryCategoryId, $ids, true)) {
                    $ids[] = $data->primaryCategoryId;
                }
                $this->categorySynchronizer->sync($locked, $data->primaryCategoryId, $ids, $data->status);
            }

            $locked->refresh()->load(['translations', 'categories', 'brand', 'primaryCategory']);

            if ($data->status === ProductStatus::Active) {
                $this->readinessService->assertReadyForActivation($locked);
                if ($locked->published_at === null) {
                    $locked->published_at = now();
                }
            }

            if ($data->status === ProductStatus::Archived) {
                throw ValidationException::withMessages([
                    'status' => ['Use the archive endpoint to archive a product.'],
                ]);
            }

            $locked->status = $data->status;
            $locked->save();

            $this->recordAuditEvent->execute(new AuditEventData(
                event: AuditEvent::ProductUpdated,
                actorUserId: (int) $actor->getAuthIdentifier(),
                subjectType: 'product',
                subjectId: (string) $locked->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: $old,
                newValues: [
                    'brand_id' => $locked->brand_id,
                    'primary_category_id' => $locked->primary_category_id,
                    'status' => $locked->status->value,
                    'is_featured' => $locked->is_featured,
                    'sort_order' => $locked->sort_order,
                    'model_number' => $locked->model_number,
                    'manufacturer_part_number' => $locked->manufacturer_part_number,
                    'locales' => $locked->translations->pluck('locale')->all(),
                    'category_ids' => $locked->categories->pluck('id')->all(),
                ],
            ));

            return $locked->fresh([
                'translations',
                'categories.translations',
                'brand.translations',
                'primaryCategory.translations',
            ]) ?? $locked;
        });

        $this->catalogCache->bump();

        return $updated;
    }

    private function assertBrand(?int $brandId, ProductStatus $status): void
    {
        if ($brandId === null) {
            return;
        }

        $brand = Brand::query()->find($brandId);
        if ($brand === null) {
            throw ValidationException::withMessages(['brand_id' => ['The selected brand is invalid.']]);
        }
        if ($brand->status === CatalogStatus::Archived) {
            throw ValidationException::withMessages(['brand_id' => ['Archived brands cannot be assigned.']]);
        }
        if ($status === ProductStatus::Active && $brand->status !== CatalogStatus::Active) {
            throw ValidationException::withMessages(['brand_id' => ['An active brand is required for activation.']]);
        }
    }

    /**
     * @param  list<ProductTranslationData>  $translations
     */
    private function syncTranslations(Product $product, array $translations, bool $removeEnglish): void
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
            $seen[$translation->locale] = true;

            $slug = CatalogSlug::normalize($translation->slug);
            if ($slug === '' || ! CatalogSlug::isValid($slug)) {
                throw ValidationException::withMessages([
                    "translations.{$index}.slug" => ['A valid slug is required.'],
                ]);
            }

            $conflict = ProductTranslation::query()
                ->where('locale', $translation->locale)
                ->where('slug', $slug)
                ->where('product_id', '!=', $product->id)
                ->exists();

            if ($conflict) {
                throw ValidationException::withMessages([
                    "translations.{$index}.slug" => ['This slug is already taken for the locale.'],
                ]);
            }

            ProductTranslation::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'locale' => $translation->locale,
                ],
                [
                    'name' => trim($translation->name),
                    'slug' => $slug,
                    'short_description' => $translation->shortDescription,
                    'description' => $this->sanitizer->sanitize($translation->description),
                    'seo_title' => $translation->seoTitle,
                    'seo_description' => $translation->seoDescription,
                ],
            );
        }

        if ($removeEnglish) {
            if ($product->status === ProductStatus::Active && ! isset($seen[CatalogLocales::default()])) {
                // still need ka
            }
            $product->translations()->where('locale', 'en')->delete();
        }

        $hasKa = $product->translations()->where('locale', CatalogLocales::default())->exists();
        if (! $hasKa && $product->status === ProductStatus::Active) {
            throw ValidationException::withMessages([
                'translations' => ['An active product cannot lose its Georgian translation.'],
            ]);
        }
    }
}
