<?php

use App\Domains\Catalog\Exceptions\ProductNotReadyException;
use App\Domains\Identity\Exceptions\AuthenticationFailedException;
use App\Domains\Identity\Exceptions\LastActiveAdminException;
use App\Domains\Shared\Exceptions\DomainException;
use App\Domains\Shared\Exceptions\ProvidesErrorDetails;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Middleware\EnsureAdminAccess;
use App\Http\Middleware\EnsureCorrelationId;
use App\Http\Middleware\EnsureHasPermission;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Support\ApiErrorResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

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

        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiErrorResponse::make(
                    $request,
                    'TOO_MANY_REQUESTS',
                    'Too many attempts. Please wait and try again.',
                    429,
                );
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
