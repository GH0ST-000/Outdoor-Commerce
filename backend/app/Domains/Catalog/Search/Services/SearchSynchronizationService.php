<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Search\Services;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Search\Contracts\SearchGateway;
use App\Domains\Catalog\Search\Enums\SearchIndexType;
use App\Domains\Catalog\Search\Support\SearchIndexNamer;
use App\Domains\Catalog\Support\CatalogLocales;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class SearchSynchronizationService
{
    public function __construct(
        private readonly SearchGateway $gateway,
        private readonly SearchDocumentFactory $documents,
        private readonly SearchIndexManager $indexes,
        private readonly SearchIndexNamer $namer,
        private readonly SearchIndexSettings $settings,
    ) {}

    public function syncProduct(int $productId): void
    {
        $remove = $this->documents->variantDocumentIdsForProduct($productId);
        $documents = $this->documents->variantDocumentsForProduct($productId);

        foreach (CatalogLocales::all() as $locale) {
            $uid = $this->indexes->uid(SearchIndexType::Variants, $locale);
            $localeIds = array_values(array_filter(
                $remove,
                fn (string $id): bool => str_ends_with($id, '_'.$locale),
            ));
            $this->gateway->deleteDocuments($uid, $localeIds);
            $payload = [];
            foreach ($documents as $document) {
                if ($document->locale === $locale) {
                    $payload[] = $document->toArray();
                }
            }
            $this->gateway->upsertDocuments($uid, $payload);
        }
    }

    public function removeProduct(int $productId): void
    {
        $ids = $this->documents->variantDocumentIdsForProduct($productId);
        foreach (CatalogLocales::all() as $locale) {
            $uid = $this->indexes->uid(SearchIndexType::Variants, $locale);
            $localeIds = array_values(array_filter(
                $ids,
                fn (string $id): bool => str_ends_with($id, '_'.$locale),
            ));
            $this->gateway->deleteDocuments($uid, $localeIds);
        }
    }

    public function syncCategory(?int $categoryId = null): void
    {
        $documents = $this->documents->categoryDocuments($categoryId);
        foreach (CatalogLocales::all() as $locale) {
            $uid = $this->indexes->uid(SearchIndexType::Categories, $locale);
            $payload = [];
            foreach ($documents as $document) {
                if ($document->locale === $locale) {
                    $payload[] = $document->toArray();
                }
            }
            $this->gateway->upsertDocuments($uid, $payload);
        }
    }

    public function removeCategory(int $categoryId): void
    {
        foreach (CatalogLocales::all() as $locale) {
            $this->gateway->deleteDocuments(
                $this->indexes->uid(SearchIndexType::Categories, $locale),
                [$this->documents->categoryDocumentId($categoryId).'_'.$locale],
            );
        }
    }

    public function syncBrand(?int $brandId = null): void
    {
        $documents = $this->documents->brandDocuments($brandId);
        foreach (CatalogLocales::all() as $locale) {
            $uid = $this->indexes->uid(SearchIndexType::Brands, $locale);
            $payload = [];
            foreach ($documents as $document) {
                if ($document->locale === $locale) {
                    $payload[] = $document->toArray();
                }
            }
            $this->gateway->upsertDocuments($uid, $payload);
        }
    }

    public function removeBrand(int $brandId): void
    {
        foreach (CatalogLocales::all() as $locale) {
            $this->gateway->deleteDocuments(
                $this->indexes->uid(SearchIndexType::Brands, $locale),
                [$this->documents->brandDocumentId($brandId).'_'.$locale],
            );
        }
    }

    /**
     * @return array{built: int, swapped: list<string>}
     */
    public function rebuild(?string $locale = null, int $chunk = 200): array
    {
        $built = 0;
        $swapped = [];
        foreach ($this->namer->locales() as $item) {
            if ($locale !== null && $item !== $locale) {
                continue;
            }
            foreach (SearchIndexType::cases() as $type) {
                $live = $this->namer->uid($type, $item);
                $build = $this->namer->rebuildUid($type, $item);
                $this->gateway->ensureIndex($build, 'id', $this->settings->for($type, $item));
                $count = $this->fillRebuild($type, $item, $build, $chunk);
                $built += $count;
                if (! $this->gateway->indexExists($live)) {
                    $this->gateway->ensureIndex($live, 'id', $this->settings->for($type, $item));
                }
                $this->gateway->swapIndexes([$live, $build]);
                $this->indexes->remember($type, $item, $live);
                $previousKey = 'search:previous:'.$this->namer->prefix().':'.$type->value.':'.$item;
                $previous = Cache::get($previousKey);
                if (is_string($previous) && $previous !== '' && $previous !== $live && $previous !== $build) {
                    $this->gateway->deleteIndex($previous);
                }
                Cache::forever($previousKey, $build);
                $swapped[] = $live;
            }
        }

        return ['built' => $built, 'swapped' => $swapped];
    }

    /**
     * @return array{missing: int, unexpected: int, locales: list<string>}
     */
    public function verify(bool $repair = false): array
    {
        $missing = 0;
        $unexpected = 0;
        $locales = [];
        foreach (CatalogLocales::all() as $locale) {
            $uid = $this->indexes->uid(SearchIndexType::Variants, $locale);
            $indexed = $this->gateway->documentIds($uid);
            $expected = [];
            Product::query()->orderBy('id')->chunkById(200, function ($products) use ($locale, &$expected): void {
                foreach ($products as $product) {
                    foreach ($this->documents->variantDocumentsForProduct((int) $product->id) as $document) {
                        if ($document->locale === $locale) {
                            $expected[] = $document->id;
                        }
                    }
                }
            });
            $missingIds = array_values(array_diff($expected, $indexed));
            $extra = array_values(array_diff($indexed, $expected));
            $missing += count($missingIds);
            $unexpected += count($extra);
            if ($missingIds !== [] || $extra !== []) {
                $locales[] = $locale;
            }
            if ($repair) {
                $productIds = [];
                foreach ($missingIds as $id) {
                    if (preg_match('/^var_(\d+)_/', $id, $match) === 1) {
                        $variantId = (int) $match[1];
                        $productId = (int) ProductVariant::query()
                            ->withTrashed()
                            ->whereKey($variantId)
                            ->value('product_id');
                        if ($productId > 0) {
                            $productIds[$productId] = $productId;
                        }
                    }
                }
                foreach ($productIds as $productId) {
                    $this->syncProduct($productId);
                }
                if ($extra !== []) {
                    $this->gateway->deleteDocuments($uid, $extra);
                }
            }
        }

        return ['missing' => $missing, 'unexpected' => $unexpected, 'locales' => $locales];
    }

    private function fillRebuild(SearchIndexType $type, string $locale, string $uid, int $chunk): int
    {
        $count = 0;
        if ($type === SearchIndexType::Variants) {
            Product::query()->orderBy('id')->chunkById($chunk, function ($products) use ($locale, $uid, &$count): void {
                $batch = [];
                foreach ($products as $product) {
                    foreach ($this->documents->variantDocumentsForProduct((int) $product->id) as $document) {
                        if ($document->locale === $locale) {
                            $batch[] = $document->toArray();
                            $count++;
                        }
                    }
                }
                $this->gateway->upsertDocuments($uid, $batch);
            });

            return $count;
        }

        $documents = $type === SearchIndexType::Categories
            ? $this->documents->categoryDocuments()
            : $this->documents->brandDocuments();
        $batch = [];
        foreach ($documents as $document) {
            if ($document->locale === $locale) {
                $batch[] = $document->toArray();
                $count++;
            }
        }
        $this->gateway->upsertDocuments($uid, $batch);

        return $count;
    }

    public function debounceProduct(int $productId): bool
    {
        $seconds = (int) config('search.inventory_debounce_seconds', 15);
        $key = 'search:debounce:product:'.$productId;

        return Cache::add($key, 1, $seconds);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function logFailure(string $action, array $context, \Throwable $exception): void
    {
        Log::error('search.job_failed', [
            'module' => 'search',
            'action' => $action,
            'message' => $exception->getMessage(),
            ...$context,
        ]);
    }
}
