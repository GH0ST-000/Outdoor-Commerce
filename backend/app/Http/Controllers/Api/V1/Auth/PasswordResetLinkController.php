<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domains\Shared\Support\CorrelationId;
use App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

final class PasswordResetLinkController
{
    public function store(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->only('email'));

        return response()->json([
            'data' => [
                'status' => 'accepted',
                'message' => 'If an account exists for that email, password reset instructions have been sent.',
            ],
            'meta' => [
                'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
            ],
        ], 202);
    }
}
