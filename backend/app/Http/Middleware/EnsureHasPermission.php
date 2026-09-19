<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Support\ApiErrorResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureHasPermission
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if ($user === null) {
            return ApiErrorResponse::make(
                $request,
                'UNAUTHORIZED',
                'Authentication is required.',
                401,
            );
        }

        if (! $user->can($permission)) {
            return ApiErrorResponse::make(
                $request,
                'PERMISSION_DENIED',
                'You do not have permission to perform this action.',
                403,
            );
        }

        return $next($request);
    }
}
