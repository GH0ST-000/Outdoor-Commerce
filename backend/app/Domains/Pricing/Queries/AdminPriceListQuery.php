<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Queries;

use App\Domains\Catalog\Support\CatalogLocales;
use App\Domains\Pricing\Enums\PricePeriodStatus;
use App\Domains\Pricing\Models\VariantPrice;
use App\Domains\Shared\Support\Clock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Admin prices index (variant price aggregates).
 */
final class AdminPriceListQuery
{
    private const ALLOWED_SORTS = ['updated_at', 'created_at', 'version', 'price_list_id', 'product_variant_id'];

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
                'productVariant:id,product_id,sku,barcode,combination_signature',
                'productVariant.product:id',
                'productVariant.product.translations' => fn ($q) => $q->select(['id', 'product_id', 'locale', 'name']),
                'periods' => fn ($q) => $q->orderBy('starts_at'),
            ]);

        if (! empty($filters['price_list_id'])) {
            $query->where('price_list_id', (int) $filters['price_list_id']);
        }

        if (! empty($filters['product_variant_id'])) {
            $query->where('product_variant_id', (int) $filters['product_variant_id']);
        }

        if (! empty($filters['product_id'])) {
            $query->whereHas('productVariant', fn (Builder $v) => $v->where('product_id', (int) $filters['product_id']));
        }

        if (! empty($filters['currency']) || ! empty($filters['currency_code'])) {
            $currency = strtoupper((string) ($filters['currency_code'] ?? $filters['currency']));
            $query->whereHas('priceList', fn (Builder $l) => $l->where('currency_code', $currency));
        }

        if (! empty($filters['search'])) {
            $like = '%'.addcslashes(trim((string) $filters['search']), '%_\\').'%';
            $query->whereHas('productVariant', function (Builder $variant) use ($like, $locale): void {
                $variant
                    ->where('sku', 'like', $like)
                    ->orWhere('barcode', 'like', $like)
                    ->orWhereHas('product.translations', fn (Builder $t) => $t->where('locale', $locale)->where('name', 'like', $like));
            });
        }

        if (! empty($filters['period_status'])) {
            $status = PricePeriodStatus::tryFrom((string) $filters['period_status']);
            if ($status !== null) {
                $query->whereHas('periods', fn (Builder $p) => $p->where('status', $status->value));
            }
        }

        if (! empty($filters['status'])) {
            $status = PricePeriodStatus::tryFrom((string) $filters['status']);
            if ($status !== null) {
                $query->whereHas('periods', fn (Builder $p) => $p->where('status', $status->value));
            }
        }

        if (array_key_exists('pricing_ready', $filters) && $filters['pricing_ready'] !== null && $filters['pricing_ready'] !== '') {
            $ready = filter_var($filters['pricing_ready'], FILTER_VALIDATE_BOOLEAN);
            $query->where(function (Builder $builder) use ($ready, $now): void {
                $hasEffective = function (Builder $p) use ($now): void {
                    $p->where('status', PricePeriodStatus::Published->value)
                        ->where('starts_at', '<=', $now)
                        ->where(function (Builder $inner) use ($now): void {
                            $inner->whereNull('ends_at')->orWhere('ends_at', '>', $now);
                        });
                };
                if ($ready) {
                    $builder->whereHas('periods', $hasEffective);
                } else {
                    $builder->whereDoesntHave('periods', $hasEffective);
                }
            });
        }

        if (! empty($filters['missing_price'])) {
            $query->whereDoesntHave('periods', function (Builder $p) use ($now): void {
                $p->where('status', PricePeriodStatus::Published->value)
                    ->where('starts_at', '<=', $now)
                    ->where(function (Builder $inner) use ($now): void {
                        $inner->whereNull('ends_at')->orWhere('ends_at', '>', $now);
                    });
            });
        }

        if (! empty($filters['scheduled'])) {
            $query->whereHas('periods', function (Builder $p) use ($now): void {
                $p->where('status', PricePeriodStatus::Published->value)
                    ->where('starts_at', '>', $now);
            });
        }

        return $query->orderBy($sort, $direction)->orderBy('variant_prices.id')->paginate($perPage);
    }
}
