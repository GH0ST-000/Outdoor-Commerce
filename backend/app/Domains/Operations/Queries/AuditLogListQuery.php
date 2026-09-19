<?php

declare(strict_types=1);

namespace App\Domains\Operations\Queries;

use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Operations\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class AuditLogListQuery
{
    private const ALLOWED_SORTS = [
        'created_at',
        'event',
        'actor_user_id',
    ];

    /**
     * @param  array{
     *     event?: string|null,
     *     actor_user_id?: int|null,
     *     subject_type?: string|null,
     *     subject_id?: string|null,
     *     from?: string|null,
     *     to?: string|null,
     *     sort?: string|null,
     *     direction?: string|null,
     *     per_page?: int|null,
     *     page?: int|null
     * }  $filters
     * @return LengthAwarePaginator<int, AuditLog>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 10), 1), 50);
        $sort = in_array($filters['sort'] ?? 'created_at', self::ALLOWED_SORTS, true)
            ? ($filters['sort'] ?? 'created_at')
            : 'created_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query = AuditLog::query();

        if (! empty($filters['event'])) {
            $event = AuditEvent::tryFrom((string) $filters['event']);
            if ($event !== null) {
                $query->where('event', $event->value);
            }
        }

        if (! empty($filters['actor_user_id'])) {
            $query->where('actor_user_id', (int) $filters['actor_user_id']);
        }

        if (! empty($filters['subject_type'])) {
            $query->where('subject_type', (string) $filters['subject_type']);
        }

        if (! empty($filters['subject_id'])) {
            $query->where('subject_id', (string) $filters['subject_id']);
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        /** @var Builder<AuditLog> $query */
        return $query
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
