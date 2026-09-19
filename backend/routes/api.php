<?php

declare(strict_types=1);

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\V1\Admin\AdminAuditLogController;
use App\Http\Controllers\Api\V1\Admin\AdminContextController;
use App\Http\Controllers\Api\V1\Admin\AdminRoleController;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\Admin\Attributes\AdminAttributeController;
use App\Http\Controllers\Api\V1\Admin\Attributes\AdminAttributeValueController;
use App\Http\Controllers\Api\V1\Admin\Catalog\CatalogOptionsController;
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
use App\Http\Middleware\EnsureAdminAccess;
use App\Http\Middleware\EnsureHasPermission;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

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
    });
