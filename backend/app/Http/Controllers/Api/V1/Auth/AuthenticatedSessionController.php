<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domains\Cart\Actions\MergeGuestCartAction;
use App\Domains\Cart\Support\CartLogger;
use App\Domains\Identity\Actions\AuthenticateCustomerAction;
use App\Domains\Identity\Actions\LogoutCustomerAction;
use App\Domains\Identity\Exceptions\AuthenticationFailedException;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\Api\V1\AuthenticatedUserResource;
use App\Http\Support\ApiErrorResponse;
use App\Http\Support\CartActorFactory;
use App\Http\Support\GuestCartCookie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

final class AuthenticatedSessionController
{
    public function store(
        LoginRequest $request,
        AuthenticateCustomerAction $action,
        MergeGuestCartAction $mergeCart,
        CartActorFactory $actors,
        GuestCartCookie $cookie,
        CartLogger $cartLogger,
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

        $response = (new AuthenticatedUserResource($user))
            ->response()
            ->header(
                CorrelationId::HEADER,
                (string) $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
            );

        try {
            $result = $mergeCart->execute($actors->fromRequest($request));
            $cookie->apply($response, $result->issuedGuestToken, $result->forgetGuestCookie);
        } catch (Throwable $exception) {
            $cartLogger->warning('merge_after_login_failed', [
                'exception' => $exception::class,
            ]);
        }

        return $response;
    }

    public function destroy(Request $request, LogoutCustomerAction $action): Response
    {
        $action->execute();

        return response()->noContent();
    }
}
