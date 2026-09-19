<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin;

use App\Domains\Identity\Enums\Role;
use App\Domains\Identity\Support\RolePermissionMatrix;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Role $role */
        $role = $this->resource;

        $permissions = array_map(
            static fn ($permission): string => $permission->value,
            RolePermissionMatrix::permissionsFor($role),
        );
        sort($permissions);

        return [
            'name' => $role->value,
            'description' => $role->description(),
            'permissions' => $permissions,
        ];
    }
}
