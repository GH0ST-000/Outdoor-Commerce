<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domains\Identity\Actions\ResetCustomerPasswordAction;
use App\Domains\Identity\Exceptions\AuthenticationFailedException;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use App\Http\Support\ApiErrorResponse;
use Illuminate\Http\JsonResponse;

final class NewPasswordController
{
    public function store(
        ResetPasswordRequest $request,
        ResetCustomerPasswordAction $action,
    ): JsonResponse {
        try {
            $action->execute(
                email: (string) $request->validated('email'),
                token: (string) $request->validated('token'),
                password: (string) $request->validated('password'),
            );
        } catch (AuthenticationFailedException $exception) {
            return ApiErrorResponse::make(
                $request,
                $exception->errorCode(),
                $exception->getMessage(),
                422,
            );
        }

        return response()->json([
            'data' => [
                'status' => 'password_reset',
            ],
            'meta' => [
                'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
            ],
        ]);
    }
}
