<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domains\Shared\Support\CorrelationId;
use App\Http\Resources\Api\V1\AuthenticatedUserResource;
use Illuminate\Http\Request;

final class CurrentUserController
{
    public function show(Request $request): AuthenticatedUserResource
    {
        return (new AuthenticatedUserResource($request->user()))
            ->additional([
                'meta' => [
                    'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
                ],
            ]);
    }
}
