<?php

use App\Domains\Cart\Exceptions\CartException;
use App\Domains\Cart\Exceptions\CartIdempotencyConflictException;
use App\Domains\Cart\Exceptions\CartVersionConflictException;
use App\Domains\Catalog\Exceptions\ProductNotReadyException;
use App\Domains\Catalog\Exceptions\PublicCatalogNotFoundException;
use App\Domains\Catalog\PublicApi\Services\PublicCatalogContextFactory;
use App\Domains\Catalog\Search\Exceptions\SearchUnavailableException;
use App\Domains\Checkout\Exceptions\CheckoutException;
use App\Domains\Checkout\Exceptions\CheckoutIdempotencyConflictException;
use App\Domains\Geography\Exceptions\SpatialException;
use App\Domains\Hunting\Exceptions\SpeciesException;
use App\Domains\Identity\Exceptions\AuthenticationFailedException;
use App\Domains\Identity\Exceptions\LastActiveAdminException;
use App\Domains\Inventory\Exceptions\InventoryStateConflictException;
use App\Domains\Legal\Exceptions\LegalException;
use App\Domains\Orders\Exceptions\OrderException;
use App\Domains\Orders\Exceptions\OrderIdempotencyConflictException;
use App\Domains\Payments\Exceptions\PaymentException;
use App\Domains\Payments\Exceptions\PaymentIdempotencyConflictException;
use App\Domains\Pricing\Exceptions\PricingStateConflictException;
use App\Domains\Recommendations\Exceptions\RecommendationException;
use App\Domains\Shared\Exceptions\DomainException;
use App\Domains\Shared\Exceptions\ProvidesErrorDetails;
use App\Domains\Shared\Support\CorrelationId;
use App\Domains\Shipping\Exceptions\ShipmentException;
use App\Domains\Shipping\Exceptions\ShipmentIdempotencyConflictException;
use App\Http\Middleware\EnsureAdminAccess;
use App\Http\Middleware\EnsureCorrelationId;
use App\Http\Middleware\EnsureHasPermission;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Support\ApiErrorResponse;
use App\Http\Support\CartCatalogHydrator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('inventory:expire-reservations')->everyMinute();
        $schedule->command('checkout:expire-quotes')->everyMinute();
        $schedule->command('checkout:expire-sessions')->everyFiveMinutes();
        $schedule->command('orders:expire-unpaid')->everyMinute();
        $schedule->command('payments:reconcile')->everyMinute();
        $schedule->command('shipments:reconcile')->everyFiveMinutes();
        $schedule->command('shipments:detect-stale')->hourly();
        $schedule->command('catalog:refresh-time-sensitive-projections')->everyMinute();
        $schedule->command('carts:expire')->daily();
        $schedule->command('legal:check-sources')->daily();
        $schedule->command('legal-calendar:extend-horizon')->daily()->withoutOverlapping();
        $schedule->command('legal-calendar:verify-projections')->daily()->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->validateCsrfTokens(except: [
            'api/v1/payments/webhooks/*',
            'api/v1/shipments/webhooks/*',
        ]);

        $middleware->api(prepend: [
            EnsureCorrelationId::class,
        ]);

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'admin.access' => EnsureAdminAccess::class,
            'permission' => EnsureHasPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (AuthenticationFailedException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiErrorResponse::make($request, $e->errorCode(), $e->getMessage(), 401);
            }
        });

        $exceptions->render(function (PublicCatalogNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiErrorResponse::make($request, $e->errorCode(), $e->getMessage(), 404);
            }
        });

        $exceptions->render(function (ProductNotReadyException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiErrorResponse::make(
                    $request,
                    $e->errorCode(),
                    $e->getMessage(),
                    422,
                    $e->errors(),
                );
            }
        });

        $exceptions->render(function (LastActiveAdminException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiErrorResponse::make($request, $e->errorCode(), $e->getMessage(), 422);
            }
        });

        $exceptions->render(function (InventoryStateConflictException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiErrorResponse::make(
                    $request,
                    $e->errorCode(),
                    $e->getMessage(),
                    409,
                    $e instanceof ProvidesErrorDetails ? $e->errorDetails() : null,
                );
            }
        });

        $exceptions->render(function (PricingStateConflictException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiErrorResponse::make(
                    $request,
                    $e->errorCode(),
                    $e->getMessage(),
                    409,
                    $e instanceof ProvidesErrorDetails ? $e->errorDetails() : null,
                );
            }
        });

        $exceptions->render(function (CartVersionConflictException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $locale = app(PublicCatalogContextFactory::class)->fromRequest($request)->locale;
                $cart = app(CartCatalogHydrator::class)->present($e->cart(), $locale);

                return ApiErrorResponse::make(
                    $request,
                    $e->errorCode(),
                    $e->getMessage(),
                    409,
                    ['cart' => $cart],
                );
            }
        });

        $exceptions->render(function (CartIdempotencyConflictException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiErrorResponse::make($request, $e->errorCode(), $e->getMessage(), 409);
            }
        });

        $exceptions->render(function (CheckoutIdempotencyConflictException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiErrorResponse::make($request, $e->errorCode(), $e->getMessage(), 409);
            }
        });

        $exceptions->render(function (OrderIdempotencyConflictException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiErrorResponse::make($request, $e->errorCode(), $e->getMessage(), 409);
            }
        });

        $exceptions->render(function (PaymentIdempotencyConflictException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiErrorResponse::make($request, $e->errorCode(), $e->getMessage(), 409);
            }
        });

        $exceptions->render(function (ShipmentIdempotencyConflictException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiErrorResponse::make($request, $e->errorCode(), $e->getMessage(), 409);
            }
        });

        $exceptions->render(function (ShipmentException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $status = match ($e->errorCode()) {
                    'SHIPMENT_NOT_FOUND',
                    'SHIPMENT_PROVIDER_UNKNOWN' => 404,
                    'SHIPMENT_VERSION_CONFLICT',
                    'SHIPMENT_IDEMPOTENCY_CONFLICT',
                    'SHIPMENT_ALREADY_DISPATCHED',
                    'SHIPMENT_ALREADY_DELIVERED',
                    'SHIPMENT_ALREADY_COLLECTED' => 409,
                    'SHIPMENT_SIGNATURE_INVALID' => 400,
                    'SHIPMENT_PAYLOAD_TOO_LARGE' => 413,
                    default => 422,
                };

                return ApiErrorResponse::make(
                    $request,
                    $e->errorCode(),
                    $e->getMessage(),
                    $status,
                    $e->errorDetails() !== [] ? $e->errorDetails() : null,
                );
            }
        });

        $exceptions->render(function (SpeciesException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiErrorResponse::make(
                    $request,
                    $e->errorCode(),
                    $e->getMessage(),
                    $e->httpStatus(),
                    $e->errorDetails() !== [] ? $e->errorDetails() : null,
                );
            }
        });

        $exceptions->render(function (RecommendationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiErrorResponse::make(
                    $request,
                    $e->errorCode(),
                    $e->getMessage(),
                    $e->httpStatus(),
                    $e->errorDetails() !== [] ? $e->errorDetails() : null,
                );
            }
        });

        $exceptions->render(function (LegalException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiErrorResponse::make(
                    $request,
                    $e->errorCode(),
                    $e->getMessage(),
                    $e->httpStatus(),
                    $e->errorDetails() !== [] ? $e->errorDetails() : null,
                );
            }
        });

        $exceptions->render(function (SpatialException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiErrorResponse::make(
                    $request,
                    $e->errorCode(),
                    $e->getMessage(),
                    $e->httpStatus(),
                    $e->errorDetails() !== [] ? $e->errorDetails() : null,
                );
            }
        });

        $exceptions->render(function (PaymentException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $status = match ($e->errorCode()) {
                    'PAYMENT_NOT_FOUND',
                    'PAYMENT_ORDER_NOT_FOUND',
                    'PAYMENT_PROVIDER_UNKNOWN' => 404,
                    'PAYMENT_IDEMPOTENCY_CONFLICT',
                    'PAYMENT_ACTIVE_ATTEMPT_EXISTS',
                    'PAYMENT_ALREADY_PAID',
                    'PAYMENT_VERSION_CONFLICT' => 409,
                    'PAYMENT_SIGNATURE_INVALID' => 400,
                    'PAYMENT_PAYLOAD_TOO_LARGE' => 413,
                    'PAYMENT_TEST_PROVIDER_FORBIDDEN',
                    'PAYMENT_SIMULATE_FORBIDDEN' => 403,
                    default => 422,
                };

                return ApiErrorResponse::make(
                    $request,
                    $e->errorCode(),
                    $e->getMessage(),
                    $status,
                    $e->errorDetails() !== [] ? $e->errorDetails() : null,
                );
            }
        });

        $exceptions->render(function (OrderException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $status = match ($e->errorCode()) {
                    'ORDER_NOT_FOUND',
                    'ORDER_CHECKOUT_NOT_FOUND',
                    'ORDER_QUOTE_NOT_FOUND' => 404,
                    'ORDER_VERSION_CONFLICT',
                    'ORDER_IDEMPOTENCY_CONFLICT',
                    'ORDER_QUOTE_ALREADY_CONSUMED',
                    'ORDER_CHECKOUT_ALREADY_CONVERTED',
                    'ORDER_QUOTE_SUPERSEDED' => 409,
                    default => 422,
                };

                return ApiErrorResponse::make(
                    $request,
                    $e->errorCode(),
                    $e->getMessage(),
                    $status,
                    $e->errorDetails() !== [] ? $e->errorDetails() : null,
                );
            }
        });

        $exceptions->render(function (CheckoutException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $status = match ($e->errorCode()) {
                    'CHECKOUT_SESSION_NOT_FOUND' => 404,
                    'CHECKOUT_VERSION_CONFLICT',
                    'CHECKOUT_CART_CHANGED',
                    'CHECKOUT_QUOTE_SUPERSEDED',
                    'CHECKOUT_IDEMPOTENCY_CONFLICT' => 409,
                    default => 422,
                };

                return ApiErrorResponse::make(
                    $request,
                    $e->errorCode(),
                    $e->getMessage(),
                    $status,
                    $e->errorDetails() !== [] ? $e->errorDetails() : null,
                );
            }
        });

        $exceptions->render(function (CartException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $status = match ($e->errorCode()) {
                    'CART_ITEM_NOT_FOUND' => 404,
                    'CART_NOT_MUTABLE' => 409,
                    default => 422,
                };

                return ApiErrorResponse::make($request, $e->errorCode(), $e->getMessage(), $status);
            }
        });

        $exceptions->render(function (DomainException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiErrorResponse::make(
                    $request,
                    $e->errorCode(),
                    $e->getMessage(),
                    422,
                    $e instanceof ProvidesErrorDetails ? $e->errorDetails() : null,
                );
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiErrorResponse::make($request, 'UNAUTHORIZED', 'Authentication is required.', 401);
            }
        });

        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiErrorResponse::make(
                    $request,
                    'CSRF_TOKEN_MISMATCH',
                    'Your session expired. Please try again.',
                    419,
                );
            }
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiErrorResponse::make(
                    $request,
                    'CSRF_TOKEN_MISMATCH',
                    'Your session expired. Please try again.',
                    419,
                );
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiErrorResponse::make($request, 'FORBIDDEN', $e->getMessage() !== '' ? $e->getMessage() : 'This action is unauthorized.', 403);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiErrorResponse::make(
                    $request,
                    'VALIDATION_FAILED',
                    'The submitted data is invalid.',
                    422,
                    $e->errors(),
                );
            }
        });

        $exceptions->render(function (SearchUnavailableException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiErrorResponse::make(
                    $request,
                    $e->errorCode(),
                    'Search is temporarily unavailable.',
                    503,
                );
            }
        });

        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $code = match (true) {
                    $request->is('api/v1/species*') => 'SPECIES_RATE_LIMITED',
                    $request->is('api/v1/legal*') => 'LEGAL_RATE_LIMITED',
                    $request->is('api/v1/search*') => 'SEARCH_RATE_LIMITED',
                    $request->is('api/v1/catalog/*') => 'CATALOG_RATE_LIMITED',
                    $request->is('api/v1/cart*') => 'CART_RATE_LIMITED',
                    $request->is('api/v1/checkout*') => 'CHECKOUT_RATE_LIMITED',
                    default => 'TOO_MANY_REQUESTS',
                };

                $response = ApiErrorResponse::make(
                    $request,
                    $code,
                    'Too many attempts. Please wait and try again.',
                    429,
                );

                foreach ($e->getHeaders() as $key => $value) {
                    $response->headers->set($key, $value);
                }

                return $response;
            }
        });

        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $correlationId = $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE);

            if (is_string($correlationId) && $correlationId !== '') {
                $response->headers->set(CorrelationId::HEADER, $correlationId);
            }

            return $response;
        });
    })->create();
