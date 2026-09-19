<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domains\Identity\Actions\RegisterCustomerAction;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Requests\Api\V1\Auth\RegisterCustomerRequest;
use App\Http\Resources\Api\V1\AuthenticatedUserResource;
use Illuminate\Http\JsonResponse;

final class RegistrationController
{
    public function store(
        RegisterCustomerRequest $request,
        RegisterCustomerAction $action,
    ): JsonResponse {
        $user = $action->execute($request->toData());

        return (new AuthenticatedUserResource($user))
            ->response()
            ->setStatusCode(201)
            ->header(
                CorrelationId::HEADER,
                (string) $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
            );
    }
}
