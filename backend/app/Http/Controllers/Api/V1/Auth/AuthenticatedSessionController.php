<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domains\Identity\Actions\AuthenticateCustomerAction;
use App\Domains\Identity\Actions\LogoutCustomerAction;
use App\Domains\Identity\Exceptions\AuthenticationFailedException;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\Api\V1\AuthenticatedUserResource;
use App\Http\Support\ApiErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class AuthenticatedSessionController
{
    public function store(
        LoginRequest $request,
        AuthenticateCustomerAction $action,
    ): JsonResponse {
        try {
            $user = $action->execute($request->toData());
        } catch (AuthenticationFailedException $exception) {
            return ApiErrorResponse::make(
                $request,
                $exception->errorCode(),
                $exception->getMessage(),
                401,
            );
        }

        return (new AuthenticatedUserResource($user))
            ->response()
            ->header(
                CorrelationId::HEADER,
                (string) $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
            );
    }

    public function destroy(Request $request, LogoutCustomerAction $action): Response
    {
        $action->execute();

        return response()->noContent();
    }
}
