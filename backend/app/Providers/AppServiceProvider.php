<?php

namespace App\Providers;

use App\Domains\Cart\Contracts\CartOwnerResolver;
use App\Domains\Cart\Services\CartResolver;
use App\Domains\Catalog\Contracts\CatalogProductLookup;
use App\Domains\Catalog\Contracts\SearchGateway as CatalogSearchGateway;
use App\Domains\Catalog\Events\CatalogAttributeChanged;
use App\Domains\Catalog\Events\CatalogBrandChanged;
use App\Domains\Catalog\Events\CatalogCategoryChanged;
use App\Domains\Catalog\Events\CatalogMediaChanged;
use App\Domains\Catalog\Events\CatalogProductChanged;
use App\Domains\Catalog\Events\CatalogVariantChanged;
use App\Domains\Catalog\Models\Attribute as CatalogAttribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\Brand;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\MediaAsset;
use App\Domains\Catalog\Models\MediaAttachment;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductTranslation;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Policies\AttributePolicy;
use App\Domains\Catalog\Policies\AttributeValuePolicy;
use App\Domains\Catalog\Policies\MediaAttachmentPolicy;
use App\Domains\Catalog\Policies\ProductPolicy;
use App\Domains\Catalog\Policies\ProductVariantPolicy;
use App\Domains\Catalog\PublicApi\Listeners\CatalogProjectionObserver;
use App\Domains\Catalog\PublicApi\Listeners\RefreshPublicCatalogProjections;
use App\Domains\Catalog\Search\Contracts\SearchGateway;
use App\Domains\Catalog\Search\Listeners\RefreshSearchIndex;
use App\Domains\Catalog\Search\Services\MeilisearchGateway;
use App\Domains\Catalog\Services\EloquentCatalogProductLookup;
use App\Domains\Checkout\Contracts\FulfillmentQuoteProvider;
use App\Domains\Checkout\Services\ConfiguredRateFulfillmentQuoteProvider;
use App\Domains\Hunting\Events\SpeciesArchived;
use App\Domains\Hunting\Events\SpeciesChanged;
use App\Domains\Hunting\Events\SpeciesPublished;
use App\Domains\Hunting\Events\SpeciesUnpublished;
use App\Domains\Hunting\Listeners\RefreshSpeciesProjections;
use App\Domains\Hunting\Models\Species;
use App\Domains\Hunting\Policies\SpeciesPolicy;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Policies\UserPolicy;
use App\Domains\Identity\Support\EmailNormalizer;
use App\Domains\Inventory\Contracts\CheckoutInventoryService;
use App\Domains\Inventory\Contracts\InventoryAllocationStrategy;
use App\Domains\Inventory\Contracts\PublicInventoryAvailability;
use App\Domains\Inventory\Events\InventoryAdjusted;
use App\Domains\Inventory\Events\InventoryCountReconciled;
use App\Domains\Inventory\Events\InventoryOutOfStock;
use App\Domains\Inventory\Events\InventoryReceived;
use App\Domains\Inventory\Events\InventoryReservationCancelled;
use App\Domains\Inventory\Events\InventoryReservationCommitted;
use App\Domains\Inventory\Events\InventoryReservationExpired;
use App\Domains\Inventory\Events\InventoryReservationReleased;
use App\Domains\Inventory\Events\InventoryReserved;
use App\Domains\Inventory\Events\InventoryTransferred;
use App\Domains\Inventory\Models\InventoryBalance;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Policies\InventoryBalancePolicy;
use App\Domains\Inventory\Policies\InventoryReservationPolicy;
use App\Domains\Inventory\Policies\WarehousePolicy;
use App\Domains\Inventory\Services\DefaultCheckoutInventoryService;
use App\Domains\Inventory\Services\DefaultWarehouseAllocationStrategy;
use App\Domains\Inventory\Services\EloquentPublicInventoryAvailability;
use App\Domains\Legal\Models\LegalConflict;
use App\Domains\Legal\Models\LegalDocument;
use App\Domains\Legal\Models\LegalDocumentVersion;
use App\Domains\Legal\Models\LegalProvision;
use App\Domains\Legal\Models\LegalRule;
use App\Domains\Legal\Models\LegalSource;
use App\Domains\Legal\Policies\LegalPolicy;
use App\Domains\Orders\Events\OrderCancelled;
use App\Domains\Orders\Events\OrderExpired;
use App\Domains\Payments\Listeners\ClosePaymentAttemptsOnOrderClosed;
use App\Domains\Pricing\Contracts\CheckoutPriceResolver;
use App\Domains\Pricing\Contracts\ProductPricingReadiness;
use App\Domains\Pricing\Contracts\PublicCatalogPricing;
use App\Domains\Pricing\Events\PriceCancelled;
use App\Domains\Pricing\Events\PriceChanged;
use App\Domains\Pricing\Events\PricePublished;
use App\Domains\Pricing\Events\PromotionActivated;
use App\Domains\Pricing\Events\PromotionPaused;
use App\Domains\Pricing\Events\PromotionTargetsChanged;
use App\Domains\Pricing\Models\PriceList;
use App\Domains\Pricing\Models\PricePeriod;
use App\Domains\Pricing\Models\Promotion;
use App\Domains\Pricing\Policies\PriceListPolicy;
use App\Domains\Pricing\Policies\PricePeriodPolicy;
use App\Domains\Pricing\Policies\PromotionPolicy;
use App\Domains\Pricing\Services\DefaultCheckoutPriceResolver;
use App\Domains\Pricing\Services\DefaultPublicCatalogPricing;
use App\Domains\Pricing\Services\PricingReadinessService;
use App\Domains\Shared\Support\Clock;
use App\Domains\Shared\Support\SystemClock;
use App\Domains\Shipping\Models\Shipment;
use App\Domains\Shipping\Policies\ShipmentPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CartOwnerResolver::class, CartResolver::class);
        $this->app->bind(FulfillmentQuoteProvider::class, ConfiguredRateFulfillmentQuoteProvider::class);
        $this->app->bind(InventoryAllocationStrategy::class, DefaultWarehouseAllocationStrategy::class);
        $this->app->bind(CheckoutInventoryService::class, DefaultCheckoutInventoryService::class);
        $this->app->singleton(Clock::class, SystemClock::class);
        $this->app->bind(CheckoutPriceResolver::class, DefaultCheckoutPriceResolver::class);
        $this->app->bind(CatalogProductLookup::class, EloquentCatalogProductLookup::class);
        $this->app->bind(ProductPricingReadiness::class, PricingReadinessService::class);
        $this->app->bind(PublicCatalogPricing::class, DefaultPublicCatalogPricing::class);
        $this->app->bind(PublicInventoryAvailability::class, EloquentPublicInventoryAvailability::class);
        $this->app->singleton(SearchGateway::class, MeilisearchGateway::class);
        $this->app->alias(SearchGateway::class, CatalogSearchGateway::class);
    }

    public function boot(): void
    {
        if (
            $this->app->environment('production')
            && ((bool) config('payments.test.enabled', false) || (bool) config('payments.providers.test.enabled', false))
        ) {
            throw new \RuntimeException('The test payment provider cannot be enabled in production.');
        }

        $this->enforceMorphMap();
        $this->registerPublicCatalogObservers();
        $this->registerPublicCatalogListeners();
        $this->registerSearchListeners();
        $this->registerPaymentListeners();
        $this->registerSpeciesListeners();

        $cartCookie = config('cart.cookie.name');
        if (is_string($cartCookie) && $cartCookie !== '') {
            EncryptCookies::except([$cartCookie]);
        }

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(\App\Models\User::class, UserPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(CatalogAttribute::class, AttributePolicy::class);
        Gate::policy(AttributeValue::class, AttributeValuePolicy::class);
        Gate::policy(ProductVariant::class, ProductVariantPolicy::class);
        Gate::policy(MediaAttachment::class, MediaAttachmentPolicy::class);
        Gate::policy(Warehouse::class, WarehousePolicy::class);
        Gate::policy(InventoryBalance::class, InventoryBalancePolicy::class);
        Gate::policy(InventoryReservation::class, InventoryReservationPolicy::class);
        Gate::policy(PriceList::class, PriceListPolicy::class);
        Gate::policy(PricePeriod::class, PricePeriodPolicy::class);
        Gate::policy(Promotion::class, PromotionPolicy::class);
        Gate::policy(Shipment::class, ShipmentPolicy::class);
        Gate::policy(Species::class, SpeciesPolicy::class);
        Gate::policy(LegalSource::class, LegalPolicy::class);
        Gate::policy(LegalDocument::class, LegalPolicy::class);
        Gate::policy(LegalDocumentVersion::class, LegalPolicy::class);
        Gate::policy(LegalProvision::class, LegalPolicy::class);
        Gate::policy(LegalRule::class, LegalPolicy::class);
        Gate::policy(LegalConflict::class, LegalPolicy::class);

        // Password policy: min 12, mixed case, numbers.
        // Compromised-password checks run only in production (HIBP).
        Password::defaults(function () {
            $rule = Password::min(12)->mixedCase()->numbers();

            return $this->app->environment('production')
                ? $rule->uncompromised()
                : $rule;
        });

        RateLimiter::for('auth.login', function (Request $request) {
            $email = EmailNormalizer::normalize((string) $request->input('email', ''));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('auth.register', function (Request $request) {
            return Limit::perMinute(5)->by((string) $request->ip());
        });

        RateLimiter::for('auth.forgot-password', function (Request $request) {
            $email = EmailNormalizer::normalize((string) $request->input('email', ''));

            return Limit::perMinute(3)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('auth.reset-password', function (Request $request) {
            return Limit::perMinute(5)->by((string) $request->ip());
        });

        RateLimiter::for('auth.verification-resend', function (Request $request) {
            $userId = (string) optional($request->user())->getAuthIdentifier();

            return Limit::perMinute(3)->by($userId.'|'.$request->ip());
        });

        RateLimiter::for('admin.mutations', function (Request $request) {
            $userId = (string) optional($request->user())->getAuthIdentifier();

            return Limit::perMinute(30)->by($userId.'|'.$request->ip());
        });

        // Uploads are far heavier than ordinary admin writes: each accepted file
        // costs disk plus a queued decode/encode cycle.
        RateLimiter::for('admin.media-uploads', function (Request $request) {
            $userId = (string) optional($request->user())->getAuthIdentifier();

            return Limit::perMinute(20)->by($userId.'|'.$request->ip());
        });

        RateLimiter::for('admin.pricing-bulk', function (Request $request) {
            $userId = (string) optional($request->user())->getAuthIdentifier();

            return Limit::perMinute(10)->by($userId.'|'.$request->ip());
        });

        RateLimiter::for('admin.pricing-preview', function (Request $request) {
            $userId = (string) optional($request->user())->getAuthIdentifier();

            return Limit::perMinute(30)->by($userId.'|'.$request->ip());
        });

        RateLimiter::for('catalog.public', function (Request $request) {
            return Limit::perMinute((int) config('catalog.public.rate_limits.browse_per_minute', 120))
                ->by((string) $request->ip());
        });

        RateLimiter::for('catalog.public.list', function (Request $request) {
            $searching = filled($request->query('q'));
            $limit = $searching
                ? (int) config('catalog.public.rate_limits.search_per_minute', 20)
                : (int) config('catalog.public.rate_limits.list_per_minute', 60);

            return Limit::perMinute($limit)
                ->by($request->ip().'|'.($searching ? 'search' : 'list'));
        });

        RateLimiter::for('catalog.public.facets', function (Request $request) {
            return Limit::perMinute((int) config('catalog.public.rate_limits.facets_per_minute', 30))
                ->by((string) $request->ip());
        });

        RateLimiter::for('search.public', function (Request $request) {
            return Limit::perMinute((int) config('search.rate_limits.grouped_per_minute', 30))
                ->by((string) $request->ip());
        });

        RateLimiter::for('search.suggest', function (Request $request) {
            return Limit::perMinute((int) config('search.rate_limits.suggest_per_minute', 60))
                ->by((string) $request->ip());
        });

        RateLimiter::for('cart.read', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier();

            return Limit::perMinute((int) config('cart.rate_limits.read_per_minute', 60))
                ->by(($userId !== null ? 'u'.$userId : 'g'.$request->ip()));
        });

        RateLimiter::for('cart.mutate', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier();

            return Limit::perMinute((int) config('cart.rate_limits.mutate_per_minute', 30))
                ->by(($userId !== null ? 'u'.$userId : 'g'.$request->ip()));
        });

        RateLimiter::for('checkout.read', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier();

            return Limit::perMinute((int) config('checkout.rate_limits.read_per_minute', 30))
                ->by(($userId !== null ? 'u'.$userId : 'g'.$request->ip()));
        });

        RateLimiter::for('checkout.mutate', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier();

            return Limit::perMinute((int) config('checkout.rate_limits.mutate_per_minute', 20))
                ->by(($userId !== null ? 'u'.$userId : 'g'.$request->ip()));
        });

        RateLimiter::for('checkout.quote', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier();

            return Limit::perMinute((int) config('checkout.rate_limits.quote_per_minute', 8))
                ->by(($userId !== null ? 'u'.$userId : 'g'.$request->ip()));
        });

        RateLimiter::for('orders.read', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier();

            return Limit::perMinute((int) config('order.rate_limits.read_per_minute', 30))
                ->by(($userId !== null ? 'u'.$userId : 'g'.$request->ip()));
        });

        RateLimiter::for('orders.create', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier();

            return Limit::perMinute((int) config('order.rate_limits.create_per_minute', 8))
                ->by(($userId !== null ? 'u'.$userId : 'g'.$request->ip()));
        });

        RateLimiter::for('orders.cancel', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier();

            return Limit::perMinute((int) config('order.rate_limits.cancel_per_minute', 8))
                ->by(($userId !== null ? 'u'.$userId : 'g'.$request->ip()));
        });

        RateLimiter::for('payments.methods', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier();

            return Limit::perMinute((int) config('payments.rate_limits.methods_per_minute', 30))
                ->by(($userId !== null ? 'u'.$userId : 'g'.$request->ip()));
        });

        RateLimiter::for('payments.read', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier();

            return Limit::perMinute((int) config('payments.rate_limits.read_per_minute', 30))
                ->by(($userId !== null ? 'u'.$userId : 'g'.$request->ip()));
        });

        RateLimiter::for('payments.mutate', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier();

            return Limit::perMinute((int) config('payments.rate_limits.mutate_per_minute', 10))
                ->by(($userId !== null ? 'u'.$userId : 'g'.$request->ip()));
        });

        RateLimiter::for('payments.webhook', function (Request $request) {
            return Limit::perMinute((int) config('payments.rate_limits.webhook_per_minute', 120))
                ->by('wh'.$request->ip());
        });

        RateLimiter::for('payments.simulate', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier();

            return Limit::perMinute((int) config('payments.rate_limits.simulate_per_minute', 20))
                ->by(($userId !== null ? 'u'.$userId : 'g'.$request->ip()));
        });

        RateLimiter::for('shipments.customer', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier();

            return Limit::perMinute((int) config('shipping.rate_limits.customer_read_per_minute', 30))
                ->by(($userId !== null ? 'u'.$userId : 'g'.$request->ip()));
        });

        RateLimiter::for('shipments.admin-mutate', function (Request $request) {
            $userId = (string) optional($request->user())->getAuthIdentifier();

            return Limit::perMinute((int) config('shipping.rate_limits.admin_mutate_per_minute', 30))
                ->by($userId.'|'.$request->ip());
        });

        RateLimiter::for('shipments.webhook', function (Request $request) {
            return Limit::perMinute((int) config('shipping.rate_limits.webhook_per_minute', 120))
                ->by('swh'.$request->ip());
        });

        RateLimiter::for('species.public', function (Request $request) {
            return Limit::perMinute((int) config('species.rate_limits.public_per_minute', 120))
                ->by((string) $request->ip());
        });

        RateLimiter::for('species.public.list', function (Request $request) {
            $searching = filled($request->query('q'));
            $limit = $searching
                ? (int) config('species.rate_limits.search_per_minute', 20)
                : (int) config('species.rate_limits.list_per_minute', 60);

            return Limit::perMinute($limit)->by((string) $request->ip());
        });

        RateLimiter::for('legal.public', function (Request $request) {
            return Limit::perMinute((int) config('legal.rate_limits.public_per_minute', 60))
                ->by((string) $request->ip());
        });

        RateLimiter::for('legal.evaluate', function (Request $request) {
            return Limit::perMinute((int) config('legal.rate_limits.evaluate_per_minute', 20))
                ->by((string) $request->ip());
        });

        RateLimiter::for('legal.admin-download', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier() ?? 'guest';

            return Limit::perMinute((int) config('legal.rate_limits.admin_download_per_minute', 30))
                ->by($userId.'|'.$request->ip());
        });
    }

    private function registerPublicCatalogObservers(): void
    {
        $observer = $this->app->make(CatalogProjectionObserver::class);

        Product::saved(fn (Product $product) => $observer->productSaved($product));
        Product::deleted(fn (Product $product) => $observer->productDeleted($product));
        ProductTranslation::saved(fn (ProductTranslation $translation) => $observer->translationSaved($translation));
        ProductVariant::saved(fn (ProductVariant $variant) => $observer->variantSaved($variant));
        ProductVariant::deleted(fn (ProductVariant $variant) => $observer->variantDeleted($variant));
        Brand::saved(fn (Brand $brand) => $observer->brandSaved($brand));
        Category::saved(fn (Category $category) => $observer->categorySaved($category));
        CatalogAttribute::saved(fn (CatalogAttribute $attribute) => $observer->attributeSaved($attribute));
        AttributeValue::saved(fn (AttributeValue $value) => $observer->attributeValueSaved($value));
        MediaAttachment::saved(fn (MediaAttachment $attachment) => $observer->mediaAttachmentSaved($attachment));
        MediaAsset::saved(fn (MediaAsset $asset) => $observer->mediaAssetSaved($asset));
    }

    private function registerPublicCatalogListeners(): void
    {
        $listen = [
            CatalogProductChanged::class => 'productChanged',
            CatalogVariantChanged::class => 'variantChanged',
            CatalogBrandChanged::class => 'brandChanged',
            CatalogCategoryChanged::class => 'categoryChanged',
            CatalogAttributeChanged::class => 'attributeChanged',
            CatalogMediaChanged::class => 'mediaChanged',
            InventoryReserved::class => 'inventoryReserved',
            InventoryReservationReleased::class => 'inventoryReservationReleased',
            InventoryReservationCancelled::class => 'inventoryReservationCancelled',
            InventoryReservationExpired::class => 'inventoryReservationExpired',
            InventoryReservationCommitted::class => 'inventoryReservationCommitted',
            InventoryAdjusted::class => 'inventoryAdjusted',
            InventoryCountReconciled::class => 'inventoryReconciled',
            InventoryTransferred::class => 'inventoryTransferred',
            InventoryReceived::class => 'inventoryReceived',
            InventoryOutOfStock::class => 'inventoryOutOfStock',
            PriceChanged::class => 'priceChanged',
            PricePublished::class => 'pricePublished',
            PriceCancelled::class => 'priceCancelled',
            PromotionActivated::class => 'promotionChanged',
            PromotionPaused::class => 'promotionChanged',
            PromotionTargetsChanged::class => 'promotionChanged',
        ];

        foreach ($listen as $event => $method) {
            Event::listen($event, [RefreshPublicCatalogProjections::class, $method]);
        }
    }

    private function registerSearchListeners(): void
    {
        $listen = [
            CatalogProductChanged::class => 'productChanged',
            CatalogVariantChanged::class => 'variantChanged',
            CatalogBrandChanged::class => 'brandChanged',
            CatalogCategoryChanged::class => 'categoryChanged',
            CatalogAttributeChanged::class => 'attributeChanged',
            CatalogMediaChanged::class => 'mediaChanged',
            InventoryReserved::class => 'inventoryReserved',
            InventoryReservationReleased::class => 'inventoryReservationReleased',
            InventoryReservationCancelled::class => 'inventoryReservationCancelled',
            InventoryReservationExpired::class => 'inventoryReservationExpired',
            InventoryReservationCommitted::class => 'inventoryReservationCommitted',
            InventoryAdjusted::class => 'inventoryAdjusted',
            InventoryCountReconciled::class => 'inventoryReconciled',
            InventoryTransferred::class => 'inventoryTransferred',
            InventoryReceived::class => 'inventoryReceived',
            InventoryOutOfStock::class => 'inventoryOutOfStock',
            PriceChanged::class => 'priceChanged',
            PricePublished::class => 'pricePublished',
            PriceCancelled::class => 'priceCancelled',
            PromotionActivated::class => 'promotionChanged',
            PromotionPaused::class => 'promotionChanged',
            PromotionTargetsChanged::class => 'promotionChanged',
        ];

        foreach ($listen as $event => $method) {
            Event::listen($event, [RefreshSearchIndex::class, $method]);
        }
    }

    private function registerPaymentListeners(): void
    {
        Event::listen(OrderExpired::class, [ClosePaymentAttemptsOnOrderClosed::class, 'handleExpired']);
        Event::listen(OrderCancelled::class, [ClosePaymentAttemptsOnOrderClosed::class, 'handleCancelled']);
    }

    private function registerSpeciesListeners(): void
    {
        $listener = $this->app->make(RefreshSpeciesProjections::class);
        Event::listen(SpeciesChanged::class, [$listener, 'changed']);
        Event::listen(SpeciesPublished::class, [$listener, 'changed']);
        Event::listen(SpeciesUnpublished::class, [$listener, 'changed']);
        Event::listen(SpeciesArchived::class, [$listener, 'changed']);
        MediaAsset::saved(fn (MediaAsset $asset) => $listener->mediaAssetSaved($asset));
    }

    /**
     * Morph types are stored as short aliases so polymorphic rows never hard-code a
     * PHP namespace. Enforcing the map means a new morphable model fails loudly
     * here instead of silently writing a class name into the database.
     *
     * The two `User` entries are aliased to their own class names on purpose: they
     * predate the map, and remapping them would orphan every existing
     * `model_has_roles` row.
     */
    private function enforceMorphMap(): void
    {
        Relation::enforceMorphMap([
            'product' => Product::class,
            'product_variant' => ProductVariant::class,
            'species' => Species::class,
            'legal_rule' => LegalRule::class,
            'legal_source' => LegalSource::class,
            'legal_document' => LegalDocument::class,
            'legal_document_version' => LegalDocumentVersion::class,
            'legal_provision' => LegalProvision::class,
            User::class => User::class,
            \App\Models\User::class => \App\Models\User::class,
        ]);
    }
}
