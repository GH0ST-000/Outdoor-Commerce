<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Queries;

use App\Domains\Catalog\Support\CatalogLocales;
use App\Domains\Pricing\Enums\PricePeriodStatus;
use App\Domains\Pricing\Models\VariantPrice;
use App\Domains\Shared\Support\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class AdminPriceIndexQuery
{
    private const ALLOWED_SORTS = [
        'updated_at',
        'created_at',
        'price_list_id',
        'product_variant_id',
        'version',
    ];

    public function __construct(
        private readonly Clock $clock,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, VariantPrice>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 10), 1), 50);
        $locale = CatalogLocales::isSupported((string) ($filters['locale'] ?? ''))
            ? (string) $filters['locale']
            : CatalogLocales::default();
        $sort = in_array($filters['sort'] ?? 'updated_at', self::ALLOWED_SORTS, true)
            ? (string) ($filters['sort'] ?? 'updated_at')
            : 'updated_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $now = $this->clock->now();

        $query = VariantPrice::query()
            ->select('variant_prices.*')
            ->with([
                'priceList:id,code,name,currency_code,status',
                'productVariant:id,product_id,sku,barcode,combination_signature,status',
                'productVariant.product:id',
                'productVariant.product.translations' => fn ($q) => $q->select(['id', 'product_id', 'locale', 'name']),
                'periods' => fn ($q) => $q->orderByDesc('starts_at'),
            ]);

        if (! empty($filters['price_list_id'])) {
            $query->where('variant_prices.price_list_id', (int) $filters['price_list_id']);
        }

        if (! empty($filters['product_variant_id'])) {
            $query->where('variant_prices.product_variant_id', (int) $filters['product_variant_id']);
        }

        if (! empty($filters['product_id'])) {
            $query->whereHas(
                'productVariant',
                fn (Builder $variant) => $variant->where('product_id', (int) $filters['product_id']),
            );
        }

        if (! empty($filters['currency'])) {
            $query->whereHas(
                'priceList',
                fn (Builder $list) => $list->where('currency_code', strtoupper((string) $filters['currency'])),
            );
        }

        if (! empty($filters['search'])) {
            $like = '%'.addcslashes(trim((string) $filters['search']), '%_\\').'%';
            $query->whereHas('productVariant', function (Builder $variant) use ($like, $locale): void {
                $variant
                    ->where('sku', 'like', $like)
                    ->orWhere('barcode', 'like', $like)
                    ->orWhereHas(
                        'product.translations',
                        fn (Builder $t) => $t->where('locale', $locale)->where('name', 'like', $like),
                    );
            });
        }

        if (! empty($filters['period_status'])) {
            $status = PricePeriodStatus::tryFrom((string) $filters['period_status']);
            if ($status !== null) {
                $query->whereHas('periods', fn (Builder $periods) => $periods->where('status', $status->value));
            }
        }

        if (array_key_exists('pricing_ready', $filters) && $filters['pricing_ready'] !== null && $filters['pricing_ready'] !== '') {
            $ready = filter_var($filters['pricing_ready'], FILTER_VALIDATE_BOOLEAN);
            if ($ready) {
                $query->whereHas('periods', function (Builder $periods) use ($now): void {
                    $periods
                        ->where('status', PricePeriodStatus::Published->value)
                        ->where('starts_at', '<=', $now)
                        ->where(function (Builder $inner) use ($now): void {
                            $inner->whereNull('ends_at')->orWhere('ends_at', '>', $now);
                        });
                });
            } else {
                $query->whereDoesntHave('periods', function (Builder $periods) use ($now): void {
                    $periods
                        ->where('status', PricePeriodStatus::Published->value)
                        ->where('starts_at', '<=', $now)
                        ->where(function (Builder $inner) use ($now): void {
                            $inner->whereNull('ends_at')->orWhere('ends_at', '>', $now);
                        });
                });
            }
        }

        /** @var LengthAwarePaginator<int, VariantPrice> $paginator */
        $paginator = $query->orderBy($sort, $direction)->orderBy('variant_prices.id')->paginate($perPage);

        foreach ($paginator->items() as $row) {
            if ($row instanceof VariantPrice) {
                $row->setAttribute('admin_locale', $locale);
                $row->setAttribute('effective_at', $now);
            }
        }

        return $paginator;
    }

    /**
     * @return array{current: mixed, scheduled: mixed, effective_amount_minor: int|null, pricing_ready: bool}
     */
    public static function presentPeriods(VariantPrice $row, CarbonImmutable $at): array
    {
        $periods = $row->relationLoaded('periods') ? $row->periods : collect();

        $current = $periods->first(function ($period) use ($at): bool {
            return $period->status === PricePeriodStatus::Published
                && CarbonImmutable::instance($period->starts_at)->lessThanOrEqualTo($at)
                && ($period->ends_at === null || CarbonImmutable::instance($period->ends_at)->greaterThan($at));
        });

        if ($current === null) {
            $current = $periods->first(
                static fn ($period): bool => $period->status === PricePeriodStatus::Draft,
            );
        }

        $scheduled = $periods->first(function ($period) use ($at): bool {
            return $period->status === PricePeriodStatus::Published
                && CarbonImmutable::instance($period->starts_at)->greaterThan($at);
        });

        $effective = $periods->first(function ($period) use ($at): bool {
            return $period->status === PricePeriodStatus::Published
                && CarbonImmutable::instance($period->starts_at)->lessThanOrEqualTo($at)
                && ($period->ends_at === null || CarbonImmutable::instance($period->ends_at)->greaterThan($at));
        });

        return [
            'current' => $current,
            'scheduled' => $scheduled,
            'effective_amount_minor' => $effective?->amount_minor,
            'pricing_ready' => $effective !== null,
        ];
    }
}
