<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Pricing;

use App\Domains\Pricing\Enums\PricePeriodStatus;
use App\Domains\Pricing\Models\PricePeriod;
use App\Domains\Shared\Support\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PricePeriod */
final class PricePeriodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $now = app(Clock::class)->now();

        return [
            'id' => $this->id,
            'variant_price_id' => $this->variant_price_id,
            'amount_minor' => $this->amount_minor,
            'currency_code' => $this->whenLoaded('variantPrice', fn () => $this->variantPrice?->priceList?->currency_code),
            'status' => $this->status->value,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'lifecycle' => $this->lifecycleLabel($now),
        ];
    }

    private function lifecycleLabel(CarbonImmutable $at): string
    {
        return match ($this->status) {
            PricePeriodStatus::Draft => 'draft',
            PricePeriodStatus::Cancelled => 'cancelled',
            PricePeriodStatus::Published => $this->publishedLifecycle($at),
        };
    }

    private function publishedLifecycle(CarbonImmutable $at): string
    {
        $starts = CarbonImmutable::instance($this->starts_at)->utc();
        if ($starts->greaterThan($at)) {
            return 'scheduled';
        }

        if ($this->ends_at !== null && ! CarbonImmutable::instance($this->ends_at)->utc()->greaterThan($at)) {
            return 'expired';
        }

        return 'live';
    }
}
