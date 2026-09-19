<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domains\Identity\Actions\AssignUserRolesAction;
use App\Domains\Identity\Actions\ChangeUserStatusAction;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Queries\AdminUserListQuery;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Requests\Api\V1\Admin\AdminUserIndexRequest;
use App\Http\Requests\Api\V1\Admin\UpdateUserRolesRequest;
use App\Http\Requests\Api\V1\Admin\UpdateUserStatusRequest;
use App\Http\Resources\Api\V1\Admin\AdminUserResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AdminUserController
{
    use AuthorizesRequests;

    public function index(
        AdminUserIndexRequest $request,
        AdminUserListQuery $query,
    ): AnonymousResourceCollection {
        $this->authorize('viewAny', User::class);

        $paginator = $query->paginate($request->validated());

        return AdminUserResource::collection($paginator)->additional([
            'meta' => [
                'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
            ],
        ]);
    }

    public function show(Request $request, User $user): AdminUserResource
    {
        $this->authorize('view', $user);

        $user->loadMissing('roles');

        return (new AdminUserResource($user))->additional([
            'meta' => [
                'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
            ],
        ]);
    }

    public function updateStatus(
        UpdateUserStatusRequest $request,
        User $user,
        ChangeUserStatusAction $action,
    ): AdminUserResource {
        $this->authorize('manageStatus', $user);

        /** @var User $actor */
        $actor = $request->user();
        $status = UserStatus::from((string) $request->validated('status'));

        $updated = $action->execute(
            actor: $actor,
            target: $user,
            status: $status,
            requestId: $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return (new AdminUserResource($updated))->additional([
            'meta' => [
                'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
            ],
        ]);
    }

    public function updateRoles(
        UpdateUserRolesRequest $request,
        User $user,
        AssignUserRolesAction $action,
    ): AdminUserResource {
        $this->authorize('manageRoles', $user);

        /** @var User $actor */
        $actor = $request->user();

        /** @var list<string> $roles */
        $roles = $request->validated('roles');

        $updated = $action->execute(
            actor: $actor,
            target: $user,
            roleNames: $roles,
            requestId: $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return (new AdminUserResource($updated))->additional([
            'meta' => [
                'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
            ],
        ]);
    }
}
