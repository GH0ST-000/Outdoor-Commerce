<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Pricing;

use App\Domains\Pricing\Models\Promotion;
use App\Domains\Shared\Support\Clock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Promotion */
final class PromotionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $now = app(Clock::class)->now();

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status->value,
            'lifecycle' => strtolower($this->lifecycleLabel($now)),
            'discount_type' => $this->discount_type->value,
            'percentage_basis_points' => $this->percentage_basis_points,
            'fixed_amount_minor' => $this->fixed_amount_minor,
            'currency_code' => $this->currency_code,
            'priority' => $this->priority,
            'stacking_mode' => $this->stacking_mode->value,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'maximum_discount_minor' => $this->maximum_discount_minor,
            'targets' => $this->whenLoaded('targets', function () {
                return $this->targets->map(static fn ($target) => [
                    'id' => $target->id,
                    'target_type' => $target->target_type->value,
                    'target_id' => $target->target_id,
                    'mode' => $target->mode->value,
                ])->values()->all();
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
