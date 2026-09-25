<?php

declare(strict_types=1);

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\V1\Admin\AdminAuditLogController;
use App\Http\Controllers\Api\V1\Admin\AdminContextController;
use App\Http\Controllers\Api\V1\Admin\AdminRoleController;
use App\Http\Controllers\Api\V1\Admin\AdminSearchController;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\Admin\Attributes\AdminAttributeController;
use App\Http\Controllers\Api\V1\Admin\Attributes\AdminAttributeValueController;
use App\Http\Controllers\Api\V1\Admin\Catalog\CatalogOptionsController;
use App\Http\Controllers\Api\V1\Admin\Inventory\AdminInventoryAdjustmentController;
use App\Http\Controllers\Api\V1\Admin\Inventory\AdminInventoryController;
use App\Http\Controllers\Api\V1\Admin\Inventory\AdminInventoryReceiptController;
use App\Http\Controllers\Api\V1\Admin\Inventory\AdminInventoryReservationController;
use App\Http\Controllers\Api\V1\Admin\Inventory\AdminInventoryStockCountController;
use App\Http\Controllers\Api\V1\Admin\Inventory\AdminInventoryTransferController;
use App\Http\Controllers\Api\V1\Admin\Inventory\AdminWarehouseController;
use App\Http\Controllers\Api\V1\Admin\Legal\AdminLegalController;
use App\Http\Controllers\Api\V1\Admin\Legal\AdminLegalSeasonController;
use App\Http\Controllers\Api\V1\Admin\Media\AdminMediaStatusController;
use App\Http\Controllers\Api\V1\Admin\Media\AdminProductMediaController;
use App\Http\Controllers\Api\V1\Admin\Media\AdminProductVariantMediaController;
use App\Http\Controllers\Api\V1\Admin\Pricing\AdminPriceController;
use App\Http\Controllers\Api\V1\Admin\Pricing\AdminPriceListController;
use App\Http\Controllers\Api\V1\Admin\Pricing\AdminPromotionController;
use App\Http\Controllers\Api\V1\Admin\Products\AdminProductController;
use App\Http\Controllers\Api\V1\Admin\Products\AdminProductVariantAxesController;
use App\Http\Controllers\Api\V1\Admin\Products\AdminProductVariantController;
use App\Http\Controllers\Api\V1\Admin\Products\AdminProductVariantGenerationController;
use App\Http\Controllers\Api\V1\Admin\Shipping\AdminFulfillmentController;
use App\Http\Controllers\Api\V1\Admin\Species\AdminSpeciesController;
use App\Http\Controllers\Api\V1\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Api\V1\Auth\CurrentUserController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\NewPasswordController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetLinkController;
use App\Http\Controllers\Api\V1\Auth\RegistrationController;
use App\Http\Controllers\Api\V1\Cart\PublicCartController;
use App\Http\Controllers\Api\V1\Catalog\PublicBrandController;
use App\Http\Controllers\Api\V1\Catalog\PublicCategoryController;
use App\Http\Controllers\Api\V1\Catalog\PublicProductController;
use App\Http\Controllers\Api\V1\Catalog\PublicProductFacetController;
use App\Http\Controllers\Api\V1\Checkout\PublicCheckoutController;
use App\Http\Controllers\Api\V1\Legal\PublicLegalController;
use App\Http\Controllers\Api\V1\Orders\PublicOrderController;
use App\Http\Controllers\Api\V1\Orders\PublicOrderFulfillmentController;
use App\Http\Controllers\Api\V1\Outdoor\PublicOutdoorController;
use App\Http\Controllers\Api\V1\Payments\PaymentWebhookController;
use App\Http\Controllers\Api\V1\Payments\PublicPaymentAttemptController;
use App\Http\Controllers\Api\V1\Payments\PublicPaymentMethodController;
use App\Http\Controllers\Api\V1\Payments\TestPaymentSimulateController;
use App\Http\Controllers\Api\V1\Search\PublicSearchController;
use App\Http\Controllers\Api\V1\Search\PublicSearchSuggestionController;
use App\Http\Controllers\Api\V1\Shipping\ShipmentWebhookController;
use App\Http\Controllers\Api\V1\Species\PublicSpeciesController;
use App\Http\Middleware\EnsureAdminAccess;
use App\Http\Middleware\EnsureHasPermission;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::prefix('v1/catalog')->group(function (): void {
    Route::get('/categories', [PublicCategoryController::class, 'index'])
        ->middleware('throttle:catalog.public');
    Route::get('/categories/{slug}', [PublicCategoryController::class, 'show'])
        ->middleware('throttle:catalog.public')
        ->where('slug', '.*');

    Route::get('/brands', [PublicBrandController::class, 'index'])
        ->middleware('throttle:catalog.public');
    Route::get('/brands/{slug}', [PublicBrandController::class, 'show'])
        ->middleware('throttle:catalog.public')
        ->where('slug', '.*');

    Route::get('/products/facets', [PublicProductFacetController::class, 'show'])
        ->middleware('throttle:catalog.public.facets');
    Route::get('/products', [PublicProductController::class, 'index'])
        ->middleware('throttle:catalog.public.list');
    Route::get('/products/{slug}', [PublicProductController::class, 'show'])
        ->middleware('throttle:catalog.public')
        ->where('slug', '.*');
});

Route::prefix('v1/species')->group(function (): void {
    Route::get('/filters', [PublicSpeciesController::class, 'filters'])
        ->middleware('throttle:species.public');
    Route::get('/', [PublicSpeciesController::class, 'index'])
        ->middleware('throttle:species.public.list');
    Route::get('/{slug}/similar', [PublicSpeciesController::class, 'similar'])
        ->middleware('throttle:species.public')
        ->where('slug', '.*');
    Route::get('/{slug}/legal-overview', [PublicLegalController::class, 'speciesOverview'])
        ->middleware('throttle:legal.public')
        ->where('slug', '.*');
    Route::get('/{slug}/seasons', [PublicOutdoorController::class, 'speciesSeasons'])
        ->middleware('throttle:legal.calendar')
        ->where('slug', '.*');
    Route::get('/{slug}', [PublicSpeciesController::class, 'show'])
        ->middleware('throttle:species.public')
        ->where('slug', '.*');
});

Route::prefix('v1/legal')->group(function (): void {
    Route::get('/sources', [PublicLegalController::class, 'sources'])
        ->middleware('throttle:legal.public');
    Route::get('/sources/{slug}', [PublicLegalController::class, 'showSource'])
        ->middleware('throttle:legal.public');
    Route::post('/evaluate', [PublicLegalController::class, 'evaluate'])
        ->middleware('throttle:legal.evaluate');
});

Route::prefix('v1/outdoor')->group(function (): void {
    Route::get('/availability', [PublicOutdoorController::class, 'availability'])
        ->middleware('throttle:legal.calendar');
    Route::get('/calendar', [PublicOutdoorController::class, 'calendar'])
        ->middleware('throttle:legal.calendar');
    Route::get('/season-transitions', [PublicOutdoorController::class, 'transitions'])
        ->middleware('throttle:legal.calendar');
});

Route::prefix('v1/search')->group(function (): void {
    Route::get('/', PublicSearchController::class)
        ->middleware('throttle:search.public');
    Route::get('/suggestions', PublicSearchSuggestionController::class)
        ->middleware('throttle:search.suggest');
});

Route::prefix('v1/cart')->group(function (): void {
    Route::get('/', [PublicCartController::class, 'show'])
        ->middleware('throttle:cart.read');
    Route::post('/items', [PublicCartController::class, 'storeItem'])
        ->middleware('throttle:cart.mutate');
    Route::patch('/items/{cartItemPublicId}', [PublicCartController::class, 'updateItem'])
        ->middleware('throttle:cart.mutate')
        ->where('cartItemPublicId', '[0-9a-fA-F-]{36}');
    Route::delete('/items/{cartItemPublicId}', [PublicCartController::class, 'destroyItem'])
        ->middleware('throttle:cart.mutate')
        ->where('cartItemPublicId', '[0-9a-fA-F-]{36}');
    Route::delete('/', [PublicCartController::class, 'destroy'])
        ->middleware('throttle:cart.mutate');
    Route::post('/merge', [PublicCartController::class, 'merge'])
        ->middleware(['auth:sanctum', EnsureUserIsActive::class, 'throttle:cart.mutate']);
});

Route::prefix('v1/checkout/sessions')->group(function (): void {
    Route::post('/', [PublicCheckoutController::class, 'store'])
        ->middleware('throttle:checkout.mutate');
    Route::get('/{checkoutSessionPublicId}', [PublicCheckoutController::class, 'show'])
        ->middleware('throttle:checkout.read')
        ->where('checkoutSessionPublicId', '[0-9a-fA-F-]{36}');
    Route::patch('/{checkoutSessionPublicId}/contact', [PublicCheckoutController::class, 'updateContact'])
        ->middleware('throttle:checkout.mutate')
        ->where('checkoutSessionPublicId', '[0-9a-fA-F-]{36}');
    Route::patch('/{checkoutSessionPublicId}/address', [PublicCheckoutController::class, 'updateAddress'])
        ->middleware('throttle:checkout.mutate')
        ->where('checkoutSessionPublicId', '[0-9a-fA-F-]{36}');
    Route::patch('/{checkoutSessionPublicId}/fulfillment', [PublicCheckoutController::class, 'updateFulfillment'])
        ->middleware('throttle:checkout.mutate')
        ->where('checkoutSessionPublicId', '[0-9a-fA-F-]{36}');
    Route::post('/{checkoutSessionPublicId}/quote', [PublicCheckoutController::class, 'quote'])
        ->middleware('throttle:checkout.quote')
        ->where('checkoutSessionPublicId', '[0-9a-fA-F-]{36}');
    Route::delete('/{checkoutSessionPublicId}', [PublicCheckoutController::class, 'destroy'])
        ->middleware('throttle:checkout.mutate')
        ->where('checkoutSessionPublicId', '[0-9a-fA-F-]{36}');
});

Route::prefix('v1/orders')->group(function (): void {
    Route::post('/', [PublicOrderController::class, 'store'])
        ->middleware('throttle:orders.create');
    Route::get('/{orderPublicId}', [PublicOrderController::class, 'show'])
        ->middleware('throttle:orders.read')
        ->where('orderPublicId', '[0-9a-fA-F-]{36}');
    Route::post('/{orderPublicId}/cancel', [PublicOrderController::class, 'cancel'])
        ->middleware('throttle:orders.cancel')
        ->where('orderPublicId', '[0-9a-fA-F-]{36}');
    Route::get('/{orderPublicId}/fulfillment', [PublicOrderFulfillmentController::class, 'show'])
        ->middleware('throttle:shipments.customer')
        ->where('orderPublicId', '[0-9a-fA-F-]{36}');
    Route::post('/{orderPublicId}/payment-attempts', [PublicPaymentAttemptController::class, 'store'])
        ->middleware('throttle:payments.mutate')
        ->where('orderPublicId', '[0-9a-fA-F-]{36}');
    Route::get('/{orderPublicId}/payment-attempts/current', [PublicPaymentAttemptController::class, 'current'])
        ->middleware('throttle:payments.read')
        ->where('orderPublicId', '[0-9a-fA-F-]{36}');
});

Route::get('/v1/payment-methods', [PublicPaymentMethodController::class, 'index'])
    ->middleware('throttle:payments.methods');

Route::prefix('v1/payment-attempts')->group(function (): void {
    Route::get('/{paymentAttemptPublicId}', [PublicPaymentAttemptController::class, 'show'])
        ->middleware('throttle:payments.read')
        ->where('paymentAttemptPublicId', '[0-9a-fA-F-]{36}');
    Route::post('/{paymentAttemptPublicId}/cancel', [PublicPaymentAttemptController::class, 'cancel'])
        ->middleware('throttle:payments.mutate')
        ->where('paymentAttemptPublicId', '[0-9a-fA-F-]{36}');
});

Route::post('/v1/payments/webhooks/{providerCode}', [PaymentWebhookController::class, 'store'])
    ->middleware('throttle:payments.webhook')
    ->where('providerCode', '[a-z0-9_]+');

Route::post('/v1/shipments/webhooks/{providerCode}', [ShipmentWebhookController::class, 'store'])
    ->middleware('throttle:shipments.webhook')
    ->where('providerCode', '[a-z0-9_]+');

Route::post('/v1/payments/test/attempts/{paymentAttemptPublicId}/simulate', [TestPaymentSimulateController::class, 'store'])
    ->middleware('throttle:payments.simulate')
    ->where('paymentAttemptPublicId', '[0-9a-fA-F-]{36}');

Route::prefix('v1/auth')->group(function (): void {
    Route::post('/register', [RegistrationController::class, 'store'])
        ->middleware('throttle:auth.register');

    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:auth.login');

    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:auth.forgot-password');

    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:auth.reset-password');

    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('api.v1.auth.email.verify');

    Route::middleware(['auth:sanctum', EnsureUserIsActive::class])->group(function (): void {
        Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);
        Route::get('/me', [CurrentUserController::class, 'show']);
        Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
            ->middleware('throttle:auth.verification-resend');
    });
});

Route::prefix('v1/admin')
    ->middleware(['auth:sanctum', EnsureUserIsActive::class, EnsureAdminAccess::class])
    ->group(function (): void {
        Route::get('/context', [AdminContextController::class, 'show']);

        Route::get('/users', [AdminUserController::class, 'index'])
            ->middleware(EnsureHasPermission::class.':users.view');
        Route::get('/users/{user}', [AdminUserController::class, 'show'])
            ->middleware(EnsureHasPermission::class.':users.view');
        Route::patch('/users/{user}/status', [AdminUserController::class, 'updateStatus'])
            ->middleware([EnsureHasPermission::class.':users.status.manage', 'throttle:admin.mutations']);
        Route::put('/users/{user}/roles', [AdminUserController::class, 'updateRoles'])
            ->middleware([EnsureHasPermission::class.':users.roles.manage', 'throttle:admin.mutations']);

        Route::get('/roles', [AdminRoleController::class, 'index'])
            ->middleware(EnsureHasPermission::class.':roles.view');

        Route::get('/audit-logs', [AdminAuditLogController::class, 'index'])
            ->middleware(EnsureHasPermission::class.':audit-logs.view');
        Route::get('/audit-logs/{auditLog}', [AdminAuditLogController::class, 'show'])
            ->middleware(EnsureHasPermission::class.':audit-logs.view');

        Route::get('/search/status', [AdminSearchController::class, 'status'])
            ->middleware(EnsureHasPermission::class.':catalog.view');
        Route::post('/search/configure', [AdminSearchController::class, 'configure'])
            ->middleware([EnsureHasPermission::class.':catalog.manage', 'throttle:admin.mutations']);
        Route::post('/search/rebuild', [AdminSearchController::class, 'rebuild'])
            ->middleware([EnsureHasPermission::class.':catalog.manage', 'throttle:admin.mutations']);
        Route::post('/search/products/{product}/sync', [AdminSearchController::class, 'syncProduct'])
            ->middleware([EnsureHasPermission::class.':catalog.manage', 'throttle:admin.mutations'])
            ->whereNumber('product');
        Route::delete('/search/products/{product}', [AdminSearchController::class, 'removeProduct'])
            ->middleware([EnsureHasPermission::class.':catalog.manage', 'throttle:admin.mutations'])
            ->whereNumber('product');
        Route::get('/search/verify', [AdminSearchController::class, 'verify'])
            ->middleware(EnsureHasPermission::class.':catalog.view');
        Route::post('/search/verify', [AdminSearchController::class, 'verify'])
            ->middleware([EnsureHasPermission::class.':catalog.manage', 'throttle:admin.mutations']);

        Route::get('/catalog/options/categories', [CatalogOptionsController::class, 'categories'])
            ->middleware(EnsureHasPermission::class.':catalog.view');
        Route::get('/catalog/options/brands', [CatalogOptionsController::class, 'brands'])
            ->middleware(EnsureHasPermission::class.':catalog.view');

        Route::get('/products', [AdminProductController::class, 'index'])
            ->middleware(EnsureHasPermission::class.':catalog.view');
        Route::post('/products', [AdminProductController::class, 'store'])
            ->middleware([EnsureHasPermission::class.':catalog.manage', 'throttle:admin.mutations']);
        Route::get('/products/{product}', [AdminProductController::class, 'show'])
            ->middleware(EnsureHasPermission::class.':catalog.view');
        Route::patch('/products/{product}', [AdminProductController::class, 'update'])
            ->middleware([EnsureHasPermission::class.':catalog.manage', 'throttle:admin.mutations']);
        Route::patch('/products/{product}/status', [AdminProductController::class, 'updateStatus'])
            ->middleware([EnsureHasPermission::class.':catalog.manage', 'throttle:admin.mutations']);
        Route::get('/products/{product}/readiness', [AdminProductController::class, 'readiness'])
            ->middleware(EnsureHasPermission::class.':catalog.view');
        Route::delete('/products/{product}', [AdminProductController::class, 'destroy'])
            ->middleware([EnsureHasPermission::class.':catalog.manage', 'throttle:admin.mutations']);
        Route::post('/products/{product}/restore', [AdminProductController::class, 'restore'])
            ->middleware([EnsureHasPermission::class.':catalog.manage', 'throttle:admin.mutations']);

        // Day 8: attributes, attribute values, and product variants.
        Route::get('/attributes', [AdminAttributeController::class, 'index'])
            ->middleware(EnsureHasPermission::class.':catalog.view');
        Route::post('/attributes', [AdminAttributeController::class, 'store'])
            ->middleware([EnsureHasPermission::class.':catalog.manage', 'throttle:admin.mutations']);
        Route::get('/attributes/{attribute}', [AdminAttributeController::class, 'show'])
            ->middleware(EnsureHasPermission::class.':catalog.view');
        Route::patch('/attributes/{attribute}', [AdminAttributeController::class, 'update'])
            ->middleware([EnsureHasPermission::class.':catalog.manage', 'throttle:admin.mutations']);
        Route::patch('/attributes/{attribute}/status', [AdminAttributeController::class, 'updateStatus'])
            ->middleware([EnsureHasPermission::class.':catalog.manage', 'throttle:admin.mutations']);
        Route::delete('/attributes/{attribute}', [AdminAttributeController::class, 'destroy'])
            ->middleware([EnsureHasPermission::class.':catalog.manage', 'throttle:admin.mutations']);
        Route::post('/attributes/{attribute}/restore', [AdminAttributeController::class, 'restore'])
            ->middleware([EnsureHasPermission::class.':catalog.manage', 'throttle:admin.mutations']);

        Route::get('/attributes/{attribute}/values', [AdminAttributeValueController::class, 'index'])
            ->middleware(EnsureHasPermission::class.':catalog.view');
        Route::post('/attributes/{attribute}/values', [AdminAttributeValueController::class, 'store'])
            ->middleware([EnsureHasPermission::class.':catalog.manage', 'throttle:admin.mutations']);
        Route::get('/attributes/{attribute}/values/{value}', [AdminAttributeValueController::class, 'show'])
            ->middleware(EnsureHasPermission::class.':catalog.view');
        Route::patch('/attributes/{attribute}/values/{value}', [AdminAttributeValueController::class, 'update'])
            ->middleware([EnsureHasPermission::class.':catalog.manage', 'throttle:admin.mutations']);
        Route::patch('/attributes/{attribute}/values/{value}/status', [AdminAttributeValueController::class, 'updateStatus'])
            ->middleware([EnsureHasPermission::class.':catalog.manage', 'throttle:admin.mutations']);
        Route::delete('/attributes/{attribute}/values/{value}', [AdminAttributeValueController::class, 'destroy'])
            ->middleware([EnsureHasPermission::class.':catalog.manage', 'throttle:admin.mutations']);
        Route::post('/attributes/{attribute}/values/{value}/restore', [AdminAttributeValueController::class, 'restore'])
            ->middleware([EnsureHasPermission::class.':catalog.manage', 'throttle:admin.mutations']);

        Route::get('/products/{product}/variant-axes', [AdminProductVariantAxesController::class, 'show'])
            ->middleware(EnsureHasPermission::class.':catalog.view');
        Route::match(['put', 'patch'], '/products/{product}/variant-axes', [AdminProductVariantAxesController::class, 'sync'])
            ->middleware([EnsureHasPermission::class.':catalog.manage', 'throttle:admin.mutations']);

        Route::post('/products/{product}/variants/generate-preview', [AdminProductVariantGenerationController::class, 'preview'])
            ->middleware(EnsureHasPermission::class.':catalog.view');
        Route::post('/products/{product}/variants/generate', [AdminProductVariantGenerationController::class, 'generate'])
            ->middleware([EnsureHasPermission::class.':catalog.manage', 'throttle:admin.mutations']);

        Route::get('/products/{product}/variants', [AdminProductVariantController::class, 'index'])
            ->middleware(EnsureHasPermission::class.':catalog.view');
        Route::post('/products/{product}/variants', [AdminProductVariantController::class, 'store'])
            ->middleware([EnsureHasPermission::class.':catalog.manage', 'throttle:admin.mutations']);
        Route::get('/products/{product}/variants/{variant}', [AdminProductVariantController::class, 'show'])
            ->middleware(EnsureHasPermission::class.':catalog.view');
        Route::patch('/products/{product}/variants/{variant}', [AdminProductVariantController::class, 'update'])
            ->middleware([EnsureHasPermission::class.':catalog.manage', 'throttle:admin.mutations']);
        Route::patch('/products/{product}/variants/{variant}/status', [AdminProductVariantController::class, 'updateStatus'])
            ->middleware([EnsureHasPermission::class.':catalog.manage', 'throttle:admin.mutations']);
        Route::post('/products/{product}/variants/{variant}/default', [AdminProductVariantController::class, 'setDefault'])
            ->middleware([EnsureHasPermission::class.':catalog.manage', 'throttle:admin.mutations']);
        Route::delete('/products/{product}/variants/{variant}', [AdminProductVariantController::class, 'destroy'])
            ->middleware([EnsureHasPermission::class.':catalog.manage', 'throttle:admin.mutations']);
        Route::post('/products/{product}/variants/{variant}/restore', [AdminProductVariantController::class, 'restore'])
            ->middleware([EnsureHasPermission::class.':catalog.manage', 'throttle:admin.mutations']);

        // Day 9: local media pipeline. Uploads answer 202; derivatives are queued.
        Route::get('/products/{product}/media', [AdminProductMediaController::class, 'index'])
            ->middleware(EnsureHasPermission::class.':catalog.view');
        Route::post('/products/{product}/media', [AdminProductMediaController::class, 'store'])
            ->middleware([
                EnsureHasPermission::class.':catalog.manage',
                'throttle:admin.media-uploads',
            ]);
        Route::post('/products/{product}/media/reorder', [AdminProductMediaController::class, 'reorder'])
            ->middleware([EnsureHasPermission::class.':catalog.manage', 'throttle:admin.mutations']);
        Route::patch('/products/{product}/media/{attachment}', [AdminProductMediaController::class, 'update'])
            ->middleware([EnsureHasPermission::class.':catalog.manage', 'throttle:admin.mutations']);
        Route::patch('/products/{product}/media/{attachment}/primary', [AdminProductMediaController::class, 'primary'])
            ->middleware([EnsureHasPermission::class.':catalog.manage', 'throttle:admin.mutations']);
        Route::delete('/products/{product}/media/{attachment}', [AdminProductMediaController::class, 'destroy'])
            ->middleware([EnsureHasPermission::class.':catalog.manage', 'throttle:admin.mutations']);
        Route::post('/products/{product}/media/{attachment}/retry', [AdminProductMediaController::class, 'retry'])
            ->middleware([EnsureHasPermission::class.':catalog.manage', 'throttle:admin.mutations']);

        Route::get('/products/{product}/variants/{variant}/media', [AdminProductVariantMediaController::class, 'index'])
            ->middleware(EnsureHasPermission::class.':catalog.view');
        Route::post('/products/{product}/variants/{variant}/media', [AdminProductVariantMediaController::class, 'store'])
            ->middleware([
                EnsureHasPermission::class.':catalog.manage',
                'throttle:admin.media-uploads',
            ]);
        Route::post('/products/{product}/variants/{variant}/media/reorder', [AdminProductVariantMediaController::class, 'reorder'])
            ->middleware([EnsureHasPermission::class.':catalog.manage', 'throttle:admin.mutations']);
        Route::patch('/products/{product}/variants/{variant}/media/{attachment}', [AdminProductVariantMediaController::class, 'update'])
            ->middleware([EnsureHasPermission::class.':catalog.manage', 'throttle:admin.mutations']);
        Route::patch('/products/{product}/variants/{variant}/media/{attachment}/primary', [AdminProductVariantMediaController::class, 'primary'])
            ->middleware([EnsureHasPermission::class.':catalog.manage', 'throttle:admin.mutations']);
        Route::delete('/products/{product}/variants/{variant}/media/{attachment}', [AdminProductVariantMediaController::class, 'destroy'])
            ->middleware([EnsureHasPermission::class.':catalog.manage', 'throttle:admin.mutations']);
        Route::post('/products/{product}/variants/{variant}/media/{attachment}/retry', [AdminProductVariantMediaController::class, 'retry'])
            ->middleware([EnsureHasPermission::class.':catalog.manage', 'throttle:admin.mutations']);

        Route::get('/media/{asset}/status', AdminMediaStatusController::class)
            ->middleware(EnsureHasPermission::class.':catalog.view');

        // Day 10: inventory ledger, warehouses, reservations.
        Route::get('/warehouses', [AdminWarehouseController::class, 'index'])
            ->middleware(EnsureHasPermission::class.':inventory.view');
        Route::post('/warehouses', [AdminWarehouseController::class, 'store'])
            ->middleware([EnsureHasPermission::class.':inventory.manage', 'throttle:admin.mutations']);
        Route::get('/warehouses/{warehouse}', [AdminWarehouseController::class, 'show'])
            ->middleware(EnsureHasPermission::class.':inventory.view');
        Route::patch('/warehouses/{warehouse}', [AdminWarehouseController::class, 'update'])
            ->middleware([EnsureHasPermission::class.':inventory.manage', 'throttle:admin.mutations']);
        Route::patch('/warehouses/{warehouse}/status', [AdminWarehouseController::class, 'updateStatus'])
            ->middleware([EnsureHasPermission::class.':inventory.manage', 'throttle:admin.mutations']);
        Route::patch('/warehouses/{warehouse}/default', [AdminWarehouseController::class, 'updateDefault'])
            ->middleware([EnsureHasPermission::class.':inventory.manage', 'throttle:admin.mutations']);
        Route::delete('/warehouses/{warehouse}', [AdminWarehouseController::class, 'destroy'])
            ->middleware([EnsureHasPermission::class.':inventory.manage', 'throttle:admin.mutations']);
        Route::post('/warehouses/{warehouse}/restore', [AdminWarehouseController::class, 'restore'])
            ->middleware([EnsureHasPermission::class.':inventory.manage', 'throttle:admin.mutations']);

        Route::get('/inventory', [AdminInventoryController::class, 'index'])
            ->middleware(EnsureHasPermission::class.':inventory.view');

        Route::post('/inventory/receipts', [AdminInventoryReceiptController::class, 'store'])
            ->middleware([EnsureHasPermission::class.':inventory.adjust', 'throttle:admin.mutations']);
        Route::post('/inventory/adjustments', [AdminInventoryAdjustmentController::class, 'store'])
            ->middleware([EnsureHasPermission::class.':inventory.adjust', 'throttle:admin.mutations']);
        Route::post('/inventory/stock-counts', [AdminInventoryStockCountController::class, 'store'])
            ->middleware([EnsureHasPermission::class.':inventory.adjust', 'throttle:admin.mutations']);
        Route::post('/inventory/transfers', [AdminInventoryTransferController::class, 'store'])
            ->middleware([EnsureHasPermission::class.':inventory.transfer', 'throttle:admin.mutations']);

        Route::get('/inventory/reservations', [AdminInventoryReservationController::class, 'index'])
            ->middleware(EnsureHasPermission::class.':inventory.view');
        Route::get('/inventory/reservations/{reservation}', [AdminInventoryReservationController::class, 'show'])
            ->middleware(EnsureHasPermission::class.':inventory.view');
        Route::post('/inventory/reservations/{reservation}/release', [AdminInventoryReservationController::class, 'release'])
            ->middleware([EnsureHasPermission::class.':inventory.reservations.manage', 'throttle:admin.mutations']);
        Route::post('/inventory/reservations/{reservation}/cancel', [AdminInventoryReservationController::class, 'cancel'])
            ->middleware([EnsureHasPermission::class.':inventory.reservations.manage', 'throttle:admin.mutations']);

        Route::get('/inventory/{warehouse}/{variant}', [AdminInventoryController::class, 'show'])
            ->middleware(EnsureHasPermission::class.':inventory.view');
        Route::get('/inventory/{warehouse}/{variant}/ledger', [AdminInventoryController::class, 'ledger'])
            ->middleware(EnsureHasPermission::class.':inventory.view');
        Route::patch('/inventory/{warehouse}/{variant}/settings', [AdminInventoryController::class, 'updateSettings'])
            ->middleware([EnsureHasPermission::class.':inventory.manage', 'throttle:admin.mutations']);

        // Day 11: pricing engine.

        Route::get('/prices', [AdminPriceController::class, 'index'])
            ->middleware(EnsureHasPermission::class.':pricing.view');
        Route::post('/prices/bulk', [AdminPriceController::class, 'bulk'])
            ->middleware([EnsureHasPermission::class.':pricing.publish', 'throttle:admin.pricing-bulk']);
        Route::get('/price-lists', [AdminPriceListController::class, 'index'])
            ->middleware(EnsureHasPermission::class.':pricing.view');
        Route::post('/price-lists', [AdminPriceListController::class, 'store'])
            ->middleware([EnsureHasPermission::class.':pricing.manage', 'throttle:admin.mutations']);
        Route::get('/price-lists/{priceList}', [AdminPriceListController::class, 'show'])
            ->middleware(EnsureHasPermission::class.':pricing.view');
        Route::patch('/price-lists/{priceList}', [AdminPriceListController::class, 'update'])
            ->middleware([EnsureHasPermission::class.':pricing.manage', 'throttle:admin.mutations']);
        Route::patch('/price-lists/{priceList}/status', [AdminPriceListController::class, 'updateStatus'])
            ->middleware([EnsureHasPermission::class.':pricing.publish', 'throttle:admin.mutations']);
        Route::patch('/price-lists/{priceList}/default', [AdminPriceListController::class, 'updateDefault'])
            ->middleware([EnsureHasPermission::class.':pricing.publish', 'throttle:admin.mutations']);
        Route::delete('/price-lists/{priceList}', [AdminPriceListController::class, 'destroy'])
            ->middleware([EnsureHasPermission::class.':pricing.publish', 'throttle:admin.mutations']);
        Route::post('/price-lists/{priceList}/restore', [AdminPriceListController::class, 'restore'])
            ->middleware([EnsureHasPermission::class.':pricing.publish', 'throttle:admin.mutations']);

        Route::get('/price-lists/{priceList}/variants/{variant}/prices', [AdminPriceController::class, 'showVariant'])
            ->middleware(EnsureHasPermission::class.':pricing.view');
        Route::post('/price-lists/{priceList}/variants/{variant}/prices', [AdminPriceController::class, 'store'])
            ->middleware([EnsureHasPermission::class.':pricing.manage', 'throttle:admin.mutations']);
        Route::post('/price-lists/{priceList}/variants/{variant}/prices/replace', [AdminPriceController::class, 'replace'])
            ->middleware([EnsureHasPermission::class.':pricing.publish', 'throttle:admin.mutations']);

        Route::get('/price-periods/{pricePeriod}', [AdminPriceController::class, 'showPeriod'])
            ->middleware(EnsureHasPermission::class.':pricing.view');
        Route::patch('/price-periods/{pricePeriod}', [AdminPriceController::class, 'updatePeriod'])
            ->middleware([EnsureHasPermission::class.':pricing.manage', 'throttle:admin.mutations']);
        Route::post('/price-periods/{pricePeriod}/publish', [AdminPriceController::class, 'publishPeriod'])
            ->middleware([EnsureHasPermission::class.':pricing.publish', 'throttle:admin.mutations']);
        Route::post('/price-periods/{pricePeriod}/cancel', [AdminPriceController::class, 'cancelPeriod'])
            ->middleware([EnsureHasPermission::class.':pricing.publish', 'throttle:admin.mutations']);

        Route::get('/promotions', [AdminPromotionController::class, 'index'])
            ->middleware(EnsureHasPermission::class.':promotions.view');
        Route::post('/promotions', [AdminPromotionController::class, 'store'])
            ->middleware([EnsureHasPermission::class.':promotions.manage', 'throttle:admin.mutations']);
        Route::post('/promotions/preview', [AdminPromotionController::class, 'preview'])
            ->middleware([EnsureHasPermission::class.':promotions.view', 'throttle:admin.pricing-preview']);
        Route::get('/promotions/{promotion}', [AdminPromotionController::class, 'show'])
            ->middleware(EnsureHasPermission::class.':promotions.view');
        Route::patch('/promotions/{promotion}', [AdminPromotionController::class, 'update'])
            ->middleware([EnsureHasPermission::class.':promotions.manage', 'throttle:admin.mutations']);
        Route::put('/promotions/{promotion}/targets', [AdminPromotionController::class, 'syncTargets'])
            ->middleware([EnsureHasPermission::class.':promotions.manage', 'throttle:admin.mutations']);
        Route::post('/promotions/{promotion}/activate', [AdminPromotionController::class, 'activate'])
            ->middleware([EnsureHasPermission::class.':promotions.publish', 'throttle:admin.mutations']);
        Route::post('/promotions/{promotion}/pause', [AdminPromotionController::class, 'pause'])
            ->middleware([EnsureHasPermission::class.':promotions.publish', 'throttle:admin.mutations']);
        Route::post('/promotions/{promotion}/archive', [AdminPromotionController::class, 'archive'])
            ->middleware([EnsureHasPermission::class.':promotions.publish', 'throttle:admin.mutations']);
        Route::post('/promotions/{promotion}/restore', [AdminPromotionController::class, 'restore'])
            ->middleware([EnsureHasPermission::class.':promotions.publish', 'throttle:admin.mutations']);
        Route::post('/promotions/{promotion}/preview', [AdminPromotionController::class, 'previewExisting'])
            ->middleware([EnsureHasPermission::class.':promotions.view', 'throttle:admin.pricing-preview']);

        Route::get('/orders/{orderPublicId}/fulfillment', [AdminFulfillmentController::class, 'order'])
            ->middleware(EnsureHasPermission::class.':fulfillment.view')
            ->where('orderPublicId', '[0-9a-fA-F-]{36}');
        Route::post('/orders/{orderPublicId}/shipments', [AdminFulfillmentController::class, 'store'])
            ->middleware([EnsureHasPermission::class.':fulfillment.manage', 'throttle:shipments.admin-mutate'])
            ->where('orderPublicId', '[0-9a-fA-F-]{36}');
        Route::get('/shipments/{shipmentPublicId}', [AdminFulfillmentController::class, 'show'])
            ->middleware(EnsureHasPermission::class.':fulfillment.view')
            ->where('shipmentPublicId', '[0-9a-fA-F-]{36}');

        $shipmentMutations = [
            'start-preparation' => 'startPreparation',
            'pick' => 'pick',
            'pack' => 'pack',
            'ready-for-dispatch' => 'readyForDispatch',
            'dispatch' => 'dispatch',
            'mark-in-transit' => 'markInTransit',
            'mark-out-for-delivery' => 'markOutForDelivery',
            'mark-delivered' => 'markDelivered',
            'record-delivery-attempt-failed' => 'recordDeliveryAttemptFailed',
            'mark-ready-for-pickup' => 'markReadyForPickup',
            'mark-collected' => 'markCollected',
            'record-exception' => 'recordException',
            'cancel' => 'cancel',
        ];
        foreach ($shipmentMutations as $path => $action) {
            Route::post('/shipments/{shipmentPublicId}/'.$path, [AdminFulfillmentController::class, $action])
                ->middleware([EnsureHasPermission::class.':fulfillment.manage', 'throttle:shipments.admin-mutate'])
                ->where('shipmentPublicId', '[0-9a-fA-F-]{36}');
        }

        Route::get('/species', [AdminSpeciesController::class, 'index'])
            ->middleware(EnsureHasPermission::class.':species.view');
        Route::post('/species', [AdminSpeciesController::class, 'store'])
            ->middleware([EnsureHasPermission::class.':species.create', 'throttle:admin.mutations']);
        Route::get('/species/{speciesPublicId}', [AdminSpeciesController::class, 'show'])
            ->middleware(EnsureHasPermission::class.':species.view')
            ->where('speciesPublicId', '[0-9a-fA-F-]{36}');
        Route::patch('/species/{speciesPublicId}', [AdminSpeciesController::class, 'update'])
            ->middleware([EnsureHasPermission::class.':species.update', 'throttle:admin.mutations'])
            ->where('speciesPublicId', '[0-9a-fA-F-]{36}');
        Route::delete('/species/{speciesPublicId}', [AdminSpeciesController::class, 'destroy'])
            ->middleware([EnsureHasPermission::class.':species.delete', 'throttle:admin.mutations'])
            ->where('speciesPublicId', '[0-9a-fA-F-]{36}');
        Route::post('/species/{speciesPublicId}/submit-review', [AdminSpeciesController::class, 'submitReview'])
            ->middleware([EnsureHasPermission::class.':species.review', 'throttle:admin.mutations'])
            ->where('speciesPublicId', '[0-9a-fA-F-]{36}');
        Route::post('/species/{speciesPublicId}/publish', [AdminSpeciesController::class, 'publish'])
            ->middleware([EnsureHasPermission::class.':species.publish', 'throttle:admin.mutations'])
            ->where('speciesPublicId', '[0-9a-fA-F-]{36}');
        Route::post('/species/{speciesPublicId}/unpublish', [AdminSpeciesController::class, 'unpublish'])
            ->middleware([EnsureHasPermission::class.':species.publish', 'throttle:admin.mutations'])
            ->where('speciesPublicId', '[0-9a-fA-F-]{36}');
        Route::post('/species/{speciesPublicId}/archive', [AdminSpeciesController::class, 'archive'])
            ->middleware([EnsureHasPermission::class.':species.archive', 'throttle:admin.mutations'])
            ->where('speciesPublicId', '[0-9a-fA-F-]{36}');
        Route::post('/species/{speciesPublicId}/aliases', [AdminSpeciesController::class, 'aliases'])
            ->middleware([EnsureHasPermission::class.':species.manage_aliases', 'throttle:admin.mutations'])
            ->where('speciesPublicId', '[0-9a-fA-F-]{36}');
        Route::post('/species/{speciesPublicId}/sources', [AdminSpeciesController::class, 'sources'])
            ->middleware([EnsureHasPermission::class.':species.manage_sources', 'throttle:admin.mutations'])
            ->where('speciesPublicId', '[0-9a-fA-F-]{36}');
        Route::post('/species/{speciesPublicId}/similar-species', [AdminSpeciesController::class, 'similar'])
            ->middleware([EnsureHasPermission::class.':species.update', 'throttle:admin.mutations'])
            ->where('speciesPublicId', '[0-9a-fA-F-]{36}');
        Route::post('/species/{speciesPublicId}/media', [AdminSpeciesController::class, 'media'])
            ->middleware([EnsureHasPermission::class.':species.manage_media', 'throttle:admin.media-uploads'])
            ->where('speciesPublicId', '[0-9a-fA-F-]{36}');
        Route::get('/species/{speciesPublicId}/revisions', [AdminSpeciesController::class, 'revisions'])
            ->middleware(EnsureHasPermission::class.':species.view_revisions')
            ->where('speciesPublicId', '[0-9a-fA-F-]{36}');
        Route::post('/species/{speciesPublicId}/revisions/{revision}/restore', [AdminSpeciesController::class, 'restoreRevision'])
            ->middleware([EnsureHasPermission::class.':species.restore_revision', 'throttle:admin.mutations'])
            ->where('speciesPublicId', '[0-9a-fA-F-]{36}')
            ->whereNumber('revision');

        Route::get('/legal/dashboard', [AdminLegalController::class, 'dashboard'])
            ->middleware(EnsureHasPermission::class.':legal-rules.view');
        Route::get('/legal/authorities', [AdminLegalController::class, 'authorities'])
            ->middleware(EnsureHasPermission::class.':legal.sources.view');
        Route::post('/legal/authorities', [AdminLegalController::class, 'storeAuthority'])
            ->middleware([EnsureHasPermission::class.':legal.sources.manage', 'throttle:admin.mutations']);
        Route::get('/legal/sources', [AdminLegalController::class, 'sources'])
            ->middleware(EnsureHasPermission::class.':legal.sources.view');
        Route::post('/legal/sources', [AdminLegalController::class, 'storeSource'])
            ->middleware([EnsureHasPermission::class.':legal.sources.manage', 'throttle:admin.mutations']);
        Route::get('/legal/sources/{source}', [AdminLegalController::class, 'showSource'])
            ->middleware(EnsureHasPermission::class.':legal.sources.view')
            ->where('source', '[0-9a-fA-F-]{36}');
        Route::patch('/legal/sources/{source}', [AdminLegalController::class, 'updateSource'])
            ->middleware([EnsureHasPermission::class.':legal.sources.manage', 'throttle:admin.mutations'])
            ->where('source', '[0-9a-fA-F-]{36}');
        Route::post('/legal/sources/{source}/submit-review', [AdminLegalController::class, 'submitSourceReview'])
            ->middleware([EnsureHasPermission::class.':legal.sources.manage', 'throttle:admin.mutations'])
            ->where('source', '[0-9a-fA-F-]{36}');
        Route::post('/legal/sources/{source}/verify', [AdminLegalController::class, 'verifySource'])
            ->middleware([EnsureHasPermission::class.':legal.sources.verify', 'throttle:admin.mutations'])
            ->where('source', '[0-9a-fA-F-]{36}');
        Route::post('/legal/sources/{source}/reject', [AdminLegalController::class, 'rejectSource'])
            ->middleware([EnsureHasPermission::class.':legal.sources.verify', 'throttle:admin.mutations'])
            ->where('source', '[0-9a-fA-F-]{36}');
        Route::post('/legal/sources/{source}/check-for-changes', [AdminLegalController::class, 'checkSource'])
            ->middleware([EnsureHasPermission::class.':legal.sources.manage', 'throttle:admin.mutations'])
            ->where('source', '[0-9a-fA-F-]{36}');

        Route::get('/legal/documents', [AdminLegalController::class, 'documents'])
            ->middleware(EnsureHasPermission::class.':legal.documents.view');
        Route::post('/legal/documents', [AdminLegalController::class, 'storeDocument'])
            ->middleware([EnsureHasPermission::class.':legal.documents.manage', 'throttle:admin.mutations']);
        Route::get('/legal/documents/{document}', [AdminLegalController::class, 'showDocument'])
            ->middleware(EnsureHasPermission::class.':legal.documents.view')
            ->where('document', '[0-9a-fA-F-]{36}');
        Route::patch('/legal/documents/{document}', [AdminLegalController::class, 'updateDocument'])
            ->middleware([EnsureHasPermission::class.':legal.documents.manage', 'throttle:admin.mutations'])
            ->where('document', '[0-9a-fA-F-]{36}');
        Route::get('/legal/documents/{document}/versions', [AdminLegalController::class, 'documentVersions'])
            ->middleware(EnsureHasPermission::class.':legal.documents.view')
            ->where('document', '[0-9a-fA-F-]{36}');
        Route::post('/legal/documents/{document}/versions', [AdminLegalController::class, 'storeVersion'])
            ->middleware([EnsureHasPermission::class.':legal.versions.upload', 'throttle:admin.mutations'])
            ->where('document', '[0-9a-fA-F-]{36}');
        Route::get('/legal/versions/{version}', [AdminLegalController::class, 'showVersion'])
            ->middleware(EnsureHasPermission::class.':legal.documents.view')
            ->where('version', '[0-9a-fA-F-]{36}');
        Route::post('/legal/versions/{version}/submit-review', [AdminLegalController::class, 'submitVersion'])
            ->middleware([EnsureHasPermission::class.':legal.versions.upload', 'throttle:admin.mutations'])
            ->where('version', '[0-9a-fA-F-]{36}');
        Route::post('/legal/versions/{version}/approve', [AdminLegalController::class, 'approveVersion'])
            ->middleware([EnsureHasPermission::class.':legal.versions.review', 'throttle:admin.mutations'])
            ->where('version', '[0-9a-fA-F-]{36}');
        Route::post('/legal/versions/{version}/reject', [AdminLegalController::class, 'rejectVersion'])
            ->middleware([EnsureHasPermission::class.':legal.versions.review', 'throttle:admin.mutations'])
            ->where('version', '[0-9a-fA-F-]{36}');
        Route::get('/legal/versions/{version}/download', [AdminLegalController::class, 'downloadVersion'])
            ->middleware([EnsureHasPermission::class.':legal.documents.view', 'throttle:legal.admin-download'])
            ->where('version', '[0-9a-fA-F-]{36}');
        Route::get('/legal/versions/{version}/provisions', [AdminLegalController::class, 'provisions'])
            ->middleware(EnsureHasPermission::class.':legal.documents.view')
            ->where('version', '[0-9a-fA-F-]{36}');
        Route::post('/legal/versions/{version}/provisions', [AdminLegalController::class, 'storeProvision'])
            ->middleware([EnsureHasPermission::class.':legal.provisions.manage', 'throttle:admin.mutations'])
            ->where('version', '[0-9a-fA-F-]{36}');
        Route::get('/legal/provisions/{provision}', [AdminLegalController::class, 'showProvision'])
            ->middleware(EnsureHasPermission::class.':legal.documents.view')
            ->where('provision', '[0-9a-fA-F-]{36}');
        Route::patch('/legal/provisions/{provision}', [AdminLegalController::class, 'updateProvision'])
            ->middleware([EnsureHasPermission::class.':legal.provisions.manage', 'throttle:admin.mutations'])
            ->where('provision', '[0-9a-fA-F-]{36}');
        Route::delete('/legal/provisions/{provision}', [AdminLegalController::class, 'destroyProvision'])
            ->middleware([EnsureHasPermission::class.':legal.provisions.manage', 'throttle:admin.mutations'])
            ->where('provision', '[0-9a-fA-F-]{36}');

        Route::get('/legal/rules', [AdminLegalController::class, 'rules'])
            ->middleware(EnsureHasPermission::class.':legal-rules.view');
        Route::post('/legal/rules', [AdminLegalController::class, 'storeRule'])
            ->middleware([EnsureHasPermission::class.':legal.rules.create', 'throttle:admin.mutations']);
        Route::get('/legal/rules/{rule}', [AdminLegalController::class, 'showRule'])
            ->middleware(EnsureHasPermission::class.':legal-rules.view')
            ->where('rule', '[0-9a-fA-F-]{36}');
        Route::patch('/legal/rules/{rule}', [AdminLegalController::class, 'updateRule'])
            ->middleware([EnsureHasPermission::class.':legal.rules.update', 'throttle:admin.mutations'])
            ->where('rule', '[0-9a-fA-F-]{36}');
        Route::post('/legal/rules/{rule}/submit-review', [AdminLegalController::class, 'submitRule'])
            ->middleware([EnsureHasPermission::class.':legal.rules.review', 'throttle:admin.mutations'])
            ->where('rule', '[0-9a-fA-F-]{36}');
        Route::post('/legal/rules/{rule}/approve', [AdminLegalController::class, 'approveRule'])
            ->middleware([EnsureHasPermission::class.':legal.rules.review', 'throttle:admin.mutations'])
            ->where('rule', '[0-9a-fA-F-]{36}');
        Route::post('/legal/rules/{rule}/publish', [AdminLegalController::class, 'publishRule'])
            ->middleware([EnsureHasPermission::class.':legal.rules.publish', 'throttle:admin.mutations'])
            ->where('rule', '[0-9a-fA-F-]{36}');
        Route::post('/legal/rules/{rule}/reject', [AdminLegalController::class, 'rejectRule'])
            ->middleware([EnsureHasPermission::class.':legal.rules.review', 'throttle:admin.mutations'])
            ->where('rule', '[0-9a-fA-F-]{36}');
        Route::post('/legal/rules/{rule}/supersede', [AdminLegalController::class, 'supersedeRule'])
            ->middleware([EnsureHasPermission::class.':legal.rules.supersede', 'throttle:admin.mutations'])
            ->where('rule', '[0-9a-fA-F-]{36}');
        Route::post('/legal/rules/{rule}/evaluate-preview', [AdminLegalController::class, 'previewEvaluate'])
            ->middleware([EnsureHasPermission::class.':legal-rules.view', 'throttle:admin.mutations'])
            ->where('rule', '[0-9a-fA-F-]{36}');
        Route::post('/legal/evaluate', [AdminLegalController::class, 'previewEvaluateStandalone'])
            ->middleware([EnsureHasPermission::class.':legal-rules.view', 'throttle:admin.mutations']);

        Route::get('/legal/conflicts', [AdminLegalController::class, 'conflicts'])
            ->middleware(EnsureHasPermission::class.':legal.conflicts.view');
        Route::get('/legal/conflicts/{conflict}', [AdminLegalController::class, 'showConflict'])
            ->middleware(EnsureHasPermission::class.':legal.conflicts.view')
            ->where('conflict', '[0-9a-fA-F-]{36}');
        Route::post('/legal/conflicts/{conflict}/resolve', [AdminLegalController::class, 'resolveConflict'])
            ->middleware([EnsureHasPermission::class.':legal.conflicts.resolve', 'throttle:admin.mutations'])
            ->where('conflict', '[0-9a-fA-F-]{36}');
        Route::get('/legal/change-detections', [AdminLegalController::class, 'detections'])
            ->middleware(EnsureHasPermission::class.':legal.sources.view');
        Route::post('/legal/change-detections/{detection}/confirm', [AdminLegalController::class, 'confirmDetection'])
            ->middleware([EnsureHasPermission::class.':legal.sources.manage', 'throttle:admin.mutations'])
            ->where('detection', '[0-9a-fA-F-]{36}');
        Route::post('/legal/change-detections/{detection}/dismiss', [AdminLegalController::class, 'dismissDetection'])
            ->middleware([EnsureHasPermission::class.':legal.sources.manage', 'throttle:admin.mutations'])
            ->where('detection', '[0-9a-fA-F-]{36}');

        Route::get('/legal/seasons/dashboard', [AdminLegalSeasonController::class, 'dashboard'])
            ->middleware(EnsureHasPermission::class.':legal.seasons.view');
        Route::get('/legal/seasons', [AdminLegalSeasonController::class, 'index'])
            ->middleware(EnsureHasPermission::class.':legal.seasons.view');
        Route::post('/legal/seasons', [AdminLegalSeasonController::class, 'store'])
            ->middleware([EnsureHasPermission::class.':legal.seasons.create', 'throttle:admin.mutations']);
        Route::get('/legal/seasons/{season}', [AdminLegalSeasonController::class, 'show'])
            ->middleware(EnsureHasPermission::class.':legal.seasons.view')
            ->where('season', '[0-9a-fA-F-]{36}');
        Route::patch('/legal/seasons/{season}', [AdminLegalSeasonController::class, 'update'])
            ->middleware([EnsureHasPermission::class.':legal.seasons.update', 'throttle:admin.mutations'])
            ->where('season', '[0-9a-fA-F-]{36}');
        Route::post('/legal/seasons/{season}/submit-review', [AdminLegalSeasonController::class, 'submitReview'])
            ->middleware([EnsureHasPermission::class.':legal.seasons.review', 'throttle:admin.mutations'])
            ->where('season', '[0-9a-fA-F-]{36}');
        Route::post('/legal/seasons/{season}/approve', [AdminLegalSeasonController::class, 'approve'])
            ->middleware([EnsureHasPermission::class.':legal.seasons.review', 'throttle:admin.mutations'])
            ->where('season', '[0-9a-fA-F-]{36}');
        Route::post('/legal/seasons/{season}/publish', [AdminLegalSeasonController::class, 'publish'])
            ->middleware([EnsureHasPermission::class.':legal.seasons.publish', 'throttle:admin.mutations'])
            ->where('season', '[0-9a-fA-F-]{36}');
        Route::post('/legal/seasons/{season}/reject', [AdminLegalSeasonController::class, 'reject'])
            ->middleware([EnsureHasPermission::class.':legal.seasons.review', 'throttle:admin.mutations'])
            ->where('season', '[0-9a-fA-F-]{36}');
        Route::post('/legal/seasons/{season}/supersede', [AdminLegalSeasonController::class, 'supersede'])
            ->middleware([EnsureHasPermission::class.':legal.seasons.supersede', 'throttle:admin.mutations'])
            ->where('season', '[0-9a-fA-F-]{36}');
        Route::post('/legal/seasons/{season}/generate-occurrences', [AdminLegalSeasonController::class, 'generate'])
            ->middleware([EnsureHasPermission::class.':legal.seasons.generate', 'throttle:admin.mutations'])
            ->where('season', '[0-9a-fA-F-]{36}');
        Route::post('/legal/seasons/{season}/preview', [AdminLegalSeasonController::class, 'preview'])
            ->middleware([EnsureHasPermission::class.':legal.calendar.preview', 'throttle:admin.mutations'])
            ->where('season', '[0-9a-fA-F-]{36}');

        Route::get('/legal/season-occurrences', [AdminLegalSeasonController::class, 'occurrences'])
            ->middleware(EnsureHasPermission::class.':legal.seasons.view');
        Route::get('/legal/season-occurrences/{occurrence}', [AdminLegalSeasonController::class, 'showOccurrence'])
            ->middleware(EnsureHasPermission::class.':legal.seasons.view');
        Route::post('/legal/season-occurrences/regenerate', [AdminLegalSeasonController::class, 'regenerate'])
            ->middleware([EnsureHasPermission::class.':legal.seasons.generate', 'throttle:admin.mutations']);

        Route::get('/legal/season-overrides', [AdminLegalSeasonController::class, 'overrides'])
            ->middleware(EnsureHasPermission::class.':legal.season_overrides.view');
        Route::post('/legal/season-overrides', [AdminLegalSeasonController::class, 'storeOverride'])
            ->middleware([EnsureHasPermission::class.':legal.season_overrides.create', 'throttle:admin.mutations']);
        Route::get('/legal/season-overrides/{override}', [AdminLegalSeasonController::class, 'showOverride'])
            ->middleware(EnsureHasPermission::class.':legal.season_overrides.view')
            ->where('override', '[0-9a-fA-F-]{36}');
        Route::patch('/legal/season-overrides/{override}', [AdminLegalSeasonController::class, 'updateOverride'])
            ->middleware([EnsureHasPermission::class.':legal.season_overrides.create', 'throttle:admin.mutations'])
            ->where('override', '[0-9a-fA-F-]{36}');
        Route::post('/legal/season-overrides/{override}/submit-review', [AdminLegalSeasonController::class, 'submitOverride'])
            ->middleware([EnsureHasPermission::class.':legal.season_overrides.review', 'throttle:admin.mutations'])
            ->where('override', '[0-9a-fA-F-]{36}');
        Route::post('/legal/season-overrides/{override}/approve', [AdminLegalSeasonController::class, 'approveOverride'])
            ->middleware([EnsureHasPermission::class.':legal.season_overrides.review', 'throttle:admin.mutations'])
            ->where('override', '[0-9a-fA-F-]{36}');
        Route::post('/legal/season-overrides/{override}/publish', [AdminLegalSeasonController::class, 'publishOverride'])
            ->middleware([EnsureHasPermission::class.':legal.season_overrides.publish', 'throttle:admin.mutations'])
            ->where('override', '[0-9a-fA-F-]{36}');
        Route::post('/legal/season-overrides/{override}/reject', [AdminLegalSeasonController::class, 'rejectOverride'])
            ->middleware([EnsureHasPermission::class.':legal.season_overrides.review', 'throttle:admin.mutations'])
            ->where('override', '[0-9a-fA-F-]{36}');

        Route::get('/legal/calendar-generation-runs', [AdminLegalSeasonController::class, 'generationRuns'])
            ->middleware(EnsureHasPermission::class.':legal.calendar.generation_runs.view');
        Route::get('/legal/calendar-generation-runs/{run}', [AdminLegalSeasonController::class, 'showGenerationRun'])
            ->middleware(EnsureHasPermission::class.':legal.calendar.generation_runs.view');
        Route::post('/legal/calendar/evaluate-preview', [AdminLegalSeasonController::class, 'evaluatePreview'])
            ->middleware([EnsureHasPermission::class.':legal.calendar.preview', 'throttle:admin.mutations']);
        Route::get('/legal/calendar/coverage', [AdminLegalSeasonController::class, 'coverage'])
            ->middleware(EnsureHasPermission::class.':legal.calendar.coverage');
    });
