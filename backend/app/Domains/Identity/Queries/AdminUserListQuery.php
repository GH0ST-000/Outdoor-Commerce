<?php

declare(strict_types=1);

namespace App\Domains\Identity\Queries;

use App\Domains\Identity\Enums\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class AdminUserListQuery
{
    private const ALLOWED_SORTS = [
        'created_at',
        'last_login_at',
        'email',
        'first_name',
    ];

    /**
     * @param  array{
     *     search?: string|null,
     *     status?: string|null,
     *     role?: string|null,
     *     sort?: string|null,
     *     direction?: string|null,
     *     per_page?: int|null,
     *     page?: int|null
     * }  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 10), 1), 50);
        $sort = in_array($filters['sort'] ?? 'created_at', self::ALLOWED_SORTS, true)
            ? ($filters['sort'] ?? 'created_at')
            : 'created_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query = User::query()->with('roles');

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder
                    ->where('email', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$like]);
            });
        }

        if (! empty($filters['status'])) {
            $status = UserStatus::tryFrom((string) $filters['status']);
            if ($status !== null) {
                $query->where('status', $status->value);
            }
        }

        if (! empty($filters['role'])) {
            $role = Role::tryFrom((string) $filters['role']);
            if ($role !== null) {
                $query->role($role->value);
            }
        }

        return $query
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
