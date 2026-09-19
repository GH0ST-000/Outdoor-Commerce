<?php

use App\Domains\Shared\Support\CorrelationId;
use App\Http\Middleware\EnsureCorrelationId;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            EnsureCorrelationId::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $correlationId = $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE);

            if (is_string($correlationId) && $correlationId !== '') {
                $response->headers->set(CorrelationId::HEADER, $correlationId);
            }

            return $response;
        });
    })->create();
