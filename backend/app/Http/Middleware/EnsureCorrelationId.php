<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Shared\Support\CorrelationId;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class EnsureCorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = CorrelationId::tryFromHeader($request->headers->get(CorrelationId::HEADER))
            ?? CorrelationId::generate();

        $request->attributes->set(CorrelationId::REQUEST_ATTRIBUTE, $correlationId->value());

        Log::shareContext([
            'request_id' => $correlationId->value(),
        ]);

        Log::withContext([
            'request_id' => $correlationId->value(),
        ]);

        if (class_exists(Context::class)) {
            Context::add('request_id', $correlationId->value());
        }

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set(CorrelationId::HEADER, $correlationId->value());

        return $response;
    }

    /**
     * Clear request-scoped logging context for long-running workers between jobs.
     */
    public static function flushLoggingContext(): void
    {
        Log::withoutContext();

        if (class_exists(Context::class) && method_exists(Context::class, 'flush')) {
            Context::flush();
        }
    }
}
