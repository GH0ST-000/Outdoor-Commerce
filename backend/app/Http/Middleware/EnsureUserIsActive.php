<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Support\ApiErrorResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserIsActive
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user() ?? Auth::guard('web')->user();

        if ($user !== null && method_exists($user, 'isDisabled') && $user->isDisabled()) {
            Auth::guard('web')->logout();
            Auth::forgetGuards();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return ApiErrorResponse::make(
                $request,
                'UNAUTHORIZED',
                'Authentication is required.',
                401,
            );
        }

        return $next($request);
    }
}
