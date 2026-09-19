<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domains\Identity\Models\User;
use App\Domains\Shared\Support\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminContextController
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $roles = $user->getRoleNames()->sort()->values()->all();
        $permissions = $user->getAllPermissions()
            ->pluck('name')
            ->unique()
            ->sort()
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'status' => $user->status->value,
                    'email_verified' => $user->hasVerifiedEmail(),
                ],
                'roles' => $roles,
                'permissions' => $permissions,
            ],
            'meta' => [
                'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
            ],
        ]);
    }
}
