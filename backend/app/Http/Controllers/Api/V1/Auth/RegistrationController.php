<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domains\Cart\Actions\MergeGuestCartAction;
use App\Domains\Cart\Support\CartLogger;
use App\Domains\Identity\Actions\RegisterCustomerAction;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Requests\Api\V1\Auth\RegisterCustomerRequest;
use App\Http\Resources\Api\V1\AuthenticatedUserResource;
use App\Http\Support\CartActorFactory;
use App\Http\Support\GuestCartCookie;
use Illuminate\Http\JsonResponse;
use Throwable;

final class RegistrationController
{
    public function store(
        RegisterCustomerRequest $request,
        RegisterCustomerAction $action,
        MergeGuestCartAction $mergeCart,
        CartActorFactory $actors,
        GuestCartCookie $cookie,
        CartLogger $cartLogger,
    ): JsonResponse {
        $user = $action->execute($request->toData());

        $response = (new AuthenticatedUserResource($user))
            ->response()
            ->setStatusCode(201)
            ->header(
                CorrelationId::HEADER,
                (string) $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
            );

        try {
            $result = $mergeCart->execute($actors->fromRequest($request));
            $cookie->apply($response, $result->issuedGuestToken, $result->forgetGuestCookie);
        } catch (Throwable $exception) {
            $cartLogger->warning('merge_after_register_failed', [
                'exception' => $exception::class,
            ]);
        }

        return $response;
    }
}
