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
use App\Http\Controllers\Api\V1\Orders\PublicOrderController;
use App\Http\Controllers\Api\V1\Payments\PaymentWebhookController;
use App\Http\Controllers\Api\V1\Payments\PublicPaymentAttemptController;
use App\Http\Controllers\Api\V1\Payments\PublicPaymentMethodController;
use App\Http\Controllers\Api\V1\Payments\TestPaymentSimulateController;
use App\Http\Controllers\Api\V1\Search\PublicSearchController;
use App\Http\Controllers\Api\V1\Search\PublicSearchSuggestionController;
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
    });
