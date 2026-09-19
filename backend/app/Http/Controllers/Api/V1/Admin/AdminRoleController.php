<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domains\Identity\Enums\Role;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Resources\Api\V1\Admin\RoleResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AdminRoleController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $roles = collect(Role::cases())->sortBy(static fn (Role $role): string => $role->value)->values();

        return RoleResource::collection($roles)->additional([
            'meta' => [
                'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
            ],
        ]);
    }
}
