<?php

namespace App\Providers;

use App\Domains\Catalog\Contracts\CatalogProductLookup;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\MediaAttachment;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Policies\AttributePolicy;
use App\Domains\Catalog\Policies\AttributeValuePolicy;
use App\Domains\Catalog\Policies\MediaAttachmentPolicy;
use App\Domains\Catalog\Policies\ProductPolicy;
use App\Domains\Catalog\Policies\ProductVariantPolicy;
use App\Domains\Catalog\Services\EloquentCatalogProductLookup;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Policies\UserPolicy;
use App\Domains\Identity\Support\EmailNormalizer;
use App\Domains\Inventory\Contracts\CheckoutInventoryService;
use App\Domains\Inventory\Contracts\InventoryAllocationStrategy;
use App\Domains\Inventory\Models\InventoryBalance;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Policies\InventoryBalancePolicy;
use App\Domains\Inventory\Policies\InventoryReservationPolicy;
use App\Domains\Inventory\Policies\WarehousePolicy;
use App\Domains\Inventory\Services\DefaultCheckoutInventoryService;
use App\Domains\Inventory\Services\DefaultWarehouseAllocationStrategy;
use App\Domains\Pricing\Contracts\CheckoutPriceResolver;
use App\Domains\Pricing\Contracts\ProductPricingReadiness;
use App\Domains\Pricing\Models\PriceList;
use App\Domains\Pricing\Models\PricePeriod;
use App\Domains\Pricing\Models\Promotion;
use App\Domains\Pricing\Policies\PriceListPolicy;
use App\Domains\Pricing\Policies\PricePeriodPolicy;
use App\Domains\Pricing\Policies\PromotionPolicy;
use App\Domains\Pricing\Services\DefaultCheckoutPriceResolver;
use App\Domains\Pricing\Services\PricingReadinessService;
use App\Domains\Shared\Support\Clock;
use App\Domains\Shared\Support\SystemClock;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(InventoryAllocationStrategy::class, DefaultWarehouseAllocationStrategy::class);
        $this->app->bind(CheckoutInventoryService::class, DefaultCheckoutInventoryService::class);
        $this->app->singleton(Clock::class, SystemClock::class);
        $this->app->bind(CheckoutPriceResolver::class, DefaultCheckoutPriceResolver::class);
        $this->app->bind(CatalogProductLookup::class, EloquentCatalogProductLookup::class);
        $this->app->bind(ProductPricingReadiness::class, PricingReadinessService::class);
    }

    public function boot(): void
    {
        $this->enforceMorphMap();

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(\App\Models\User::class, UserPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Attribute::class, AttributePolicy::class);
        Gate::policy(AttributeValue::class, AttributeValuePolicy::class);
        Gate::policy(ProductVariant::class, ProductVariantPolicy::class);
        Gate::policy(MediaAttachment::class, MediaAttachmentPolicy::class);
        Gate::policy(Warehouse::class, WarehousePolicy::class);
        Gate::policy(InventoryBalance::class, InventoryBalancePolicy::class);
        Gate::policy(InventoryReservation::class, InventoryReservationPolicy::class);
        Gate::policy(PriceList::class, PriceListPolicy::class);
        Gate::policy(PricePeriod::class, PricePeriodPolicy::class);
        Gate::policy(Promotion::class, PromotionPolicy::class);

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
            User::class => User::class,
            \App\Models\User::class => \App\Models\User::class,
        ]);
    }
}
