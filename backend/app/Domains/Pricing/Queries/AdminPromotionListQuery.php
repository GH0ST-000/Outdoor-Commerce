<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Queries;

use App\Domains\Pricing\Enums\DiscountType;
use App\Domains\Pricing\Enums\PromotionStatus;
use App\Domains\Pricing\Enums\PromotionTargetType;
use App\Domains\Pricing\Models\Promotion;
use App\Domains\Shared\Support\Clock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class AdminPromotionListQuery
{
    private const ALLOWED_SORTS = [
        'created_at',
        'updated_at',
        'code',
        'name',
        'status',
        'priority',
        'starts_at',
        'ends_at',
    ];

    public function __construct(
        private readonly Clock $clock,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Promotion>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 10), 1), 50);
        $sort = in_array($filters['sort'] ?? 'updated_at', self::ALLOWED_SORTS, true)
            ? (string) ($filters['sort'] ?? 'updated_at')
            : 'updated_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $now = $this->clock->now();

        $query = Promotion::query()->with('targets');

        if (! empty($filters['include_deleted'])) {
            $query->withTrashed();
        }

        if (! empty($filters['status'])) {
            $status = PromotionStatus::tryFrom((string) $filters['status']);
            if ($status !== null) {
                $query->where('status', $status->value);
            }
        }

        if (! empty($filters['discount_type'])) {
            $type = DiscountType::tryFrom((string) $filters['discount_type']);
            if ($type !== null) {
                $query->where('discount_type', $type->value);
            }
        }

        if (! empty($filters['stacking_mode'])) {
            $query->where('stacking_mode', (string) $filters['stacking_mode']);
        }

        if (! empty($filters['target_type'])) {
            $targetType = PromotionTargetType::tryFrom((string) $filters['target_type']);
            if ($targetType !== null) {
                $query->whereHas(
                    'targets',
                    fn (Builder $targets) => $targets->where('target_type', $targetType->value),
                );
            }
        }

        if (! empty($filters['starts_before'])) {
            $query->where('starts_at', '<=', $filters['starts_before']);
        }
        if (! empty($filters['starts_after'])) {
            $query->where('starts_at', '>=', $filters['starts_after']);
        }
        if (! empty($filters['ends_before'])) {
            $query->where('ends_at', '<=', $filters['ends_before']);
        }
        if (! empty($filters['ends_after'])) {
            $query->where(function (Builder $builder) use ($filters): void {
                $builder->whereNull('ends_at')->orWhere('ends_at', '>=', $filters['ends_after']);
            });
        }

        if (! empty($filters['lifecycle'])) {
            $lifecycle = (string) $filters['lifecycle'];
            match ($lifecycle) {
                'draft' => $query->where('status', PromotionStatus::Draft->value),
                'paused' => $query->where('status', PromotionStatus::Paused->value),
                'archived' => $query->where('status', PromotionStatus::Archived->value),
                'scheduled' => $query
                    ->where('status', PromotionStatus::Active->value)
                    ->where('starts_at', '>', $now),
                'live' => $query
                    ->where('status', PromotionStatus::Active->value)
                    ->where('starts_at', '<=', $now)
                    ->where(function (Builder $builder) use ($now): void {
                        $builder->whereNull('ends_at')->orWhere('ends_at', '>', $now);
                    }),
                'expired' => $query
                    ->where('status', PromotionStatus::Active->value)
                    ->whereNotNull('ends_at')
                    ->where('ends_at', '<=', $now),
                default => null,
            };
        }

        if (! empty($filters['search'])) {
            $like = '%'.addcslashes(trim((string) $filters['search']), '%_\\').'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder->where('code', 'like', $like)->orWhere('name', 'like', $like);
            });
        }

        return $query->orderBy($sort, $direction)->orderBy('id')->paginate($perPage);
    }
}
