<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\PublicApi\Models\PublicCatalogProductProjection;
use App\Domains\Catalog\PublicApi\Models\PublicCatalogVariantProjection;
use App\Domains\Catalog\PublicApi\Services\PublicProductEligibility;
use App\Domains\Catalog\PublicApi\Services\PublicVariantEligibility;
use App\Domains\Inventory\Contracts\PublicInventoryAvailability;
use App\Domains\Pricing\Contracts\PublicCatalogPricing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class VerifyPublicCatalogProjectionsCommand extends Command
{
    protected $signature = 'catalog:verify-public-projections
        {--product= : Verify one product}
        {--variant= : Verify one variant}
        {--sample=50 : Number of variant rows to sample when no id is given}';

    protected $description = 'Compare public catalog projections with authoritative pricing, inventory, and eligibility';

    public function handle(
        PublicCatalogPricing $pricing,
        PublicInventoryAvailability $inventory,
        PublicProductEligibility $productEligibility,
        PublicVariantEligibility $variantEligibility,
    ): int {
        $mismatches = 0;
        $currency = (string) config('catalog.public.currency', 'GEL');
        $priceListId = $pricing->defaultPublicPriceListId($currency);
        if ($priceListId === null) {
            $this->warn('No public GEL price list is configured.');

            return self::FAILURE;
        }

        if ($this->option('variant') !== null) {
            $mismatches += $this->verifyVariant(
                (int) $this->option('variant'),
                $pricing,
                $inventory,
                $variantEligibility,
                $productEligibility,
                $priceListId,
            );
        } elseif ($this->option('product') !== null) {
            $mismatches += $this->verifyProduct((int) $this->option('product'), $productEligibility, $priceListId, $currency);
            $variantIds = ProductVariant::query()->where('product_id', (int) $this->option('product'))->pluck('id');
            foreach ($variantIds as $variantId) {
                $mismatches += $this->verifyVariant((int) $variantId, $pricing, $inventory, $variantEligibility, $productEligibility, $priceListId);
            }
        } else {
            $sample = max(1, (int) $this->option('sample'));
            $ids = PublicCatalogVariantProjection::query()->orderBy('id')->limit($sample)->pluck('product_variant_id');
            foreach ($ids as $variantId) {
                $mismatches += $this->verifyVariant((int) $variantId, $pricing, $inventory, $variantEligibility, $productEligibility, $priceListId);
            }

            $orphans = PublicCatalogVariantProjection::query()
                ->whereNotIn('product_variant_id', ProductVariant::query()->withTrashed()->select('id'))
                ->count();
            if ($orphans > 0) {
                $mismatches += $orphans;
                $this->warn("Orphaned variant projections: {$orphans}");
            }

            $missing = ProductVariant::query()
                ->whereNotIn('id', PublicCatalogVariantProjection::query()->select('product_variant_id'))
                ->count();
            if ($missing > 0) {
                $mismatches += $missing;
                $this->warn("Variants without projection: {$missing}");
            }
        }

        if ($mismatches === 0) {
            $this->info('Public catalog projections match authoritative services.');

            return self::SUCCESS;
        }

        Log::warning('public_catalog.projection_mismatch', [
            'module' => 'catalog',
            'action' => 'verify_projections',
            'mismatch_count' => $mismatches,
        ]);
        $this->error("Found {$mismatches} mismatch(es).");

        return self::FAILURE;
    }

    private function verifyVariant(
        int $variantId,
        PublicCatalogPricing $pricing,
        PublicInventoryAvailability $inventory,
        PublicVariantEligibility $variantEligibility,
        PublicProductEligibility $productEligibility,
        int $priceListId,
    ): int {
        $projection = PublicCatalogVariantProjection::query()->where('product_variant_id', $variantId)->first();
        $variant = ProductVariant::query()->withTrashed()->find($variantId);

        if ($variant === null) {
            if ($projection !== null) {
                $this->warn("Orphaned projection for variant {$variantId}");

                return 1;
            }

            return 0;
        }

        if ($projection === null) {
            $this->warn("Missing projection for variant {$variantId}");

            return 1;
        }

        $quote = $pricing->quoteVariant($variantId, $priceListId);
        $availability = $inventory->forVariant($variantId);
        $variantPublic = $variantEligibility->isPublic($variant, $priceListId);
        $productPublic = $variant->product !== null && $productEligibility->isPublic($variant->product, null, $priceListId);
        $expectedPublic = $variantPublic && $productPublic;

        $failed = false;
        if ((bool) $projection->is_public !== $expectedPublic) {
            $this->warn("Variant {$variantId}: public eligibility mismatch");
            $failed = true;
        }
        if ($quote !== null && (int) $projection->final_price_minor !== $quote->finalAmountMinor) {
            $this->warn("Variant {$variantId}: final price mismatch");
            $failed = true;
        }
        if ($quote === null && $projection->final_price_minor !== null && $expectedPublic) {
            $this->warn("Variant {$variantId}: unexpected projected price");
            $failed = true;
        }
        if ((bool) $projection->is_in_stock !== $availability->isInStock()) {
            $this->warn("Variant {$variantId}: stock status mismatch");
            $failed = true;
        }

        return $failed ? 1 : 0;
    }

    private function verifyProduct(int $productId, PublicProductEligibility $eligibility, int $priceListId, string $currency): int
    {
        $product = Product::query()->withTrashed()->find($productId);
        $projection = PublicCatalogProductProjection::query()
            ->where('product_id', $productId)
            ->where('currency_code', $currency)
            ->first();

        if ($product === null) {
            if ($projection !== null) {
                $this->warn("Orphaned product projection {$productId}");

                return 1;
            }

            return 0;
        }

        if ($projection === null) {
            $this->warn("Missing product projection {$productId}");

            return 1;
        }

        $expected = $eligibility->isPublic($product, null, $priceListId) && $projection->public_variant_count > 0;
        if ((bool) $projection->is_public !== $expected && $expected !== (bool) $projection->is_public) {
            $this->warn("Product {$productId}: public flag mismatch");

            return 1;
        }

        $publicVariants = PublicCatalogVariantProjection::query()
            ->where('product_id', $productId)
            ->where('is_public', true)
            ->get();

        $min = $publicVariants->min('final_price_minor');
        if ($publicVariants->isNotEmpty() && (int) $projection->minimum_final_price_minor !== (int) $min) {
            $this->warn("Product {$productId}: price range mismatch");

            return 1;
        }

        return 0;
    }
}
