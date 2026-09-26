<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Services;

use App\Domains\Identity\Models\User;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Recommendations\Enums\MerchandisingAdjustmentType;
use App\Domains\Recommendations\Enums\MerchandisingRuleStatus;
use App\Domains\Recommendations\Enums\RecommendationPlacement;
use App\Domains\Recommendations\Exceptions\RecommendationException;
use App\Domains\Recommendations\Models\RecommendationMerchandisingRule;
use Illuminate\Support\Str;

final class MerchandisingWriteService
{
    public function __construct(
        private readonly RecommendationAuditRecorder $audit,
        private readonly RecommendationCache $cache,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(User $actor, array $input): RecommendationMerchandisingRule
    {
        $max = (int) config('recommendations.merchandising_max_points', 12);
        $type = MerchandisingAdjustmentType::from($input['adjustment_type']);
        $value = (int) $input['adjustment_value'];
        if (in_array($type, [MerchandisingAdjustmentType::Boost, MerchandisingAdjustmentType::Demote], true) && ($value < 0 || $value > $max)) {
            throw RecommendationException::invalid('Merchandising adjustment exceeds the configured maximum.', [
                'maximum' => $max,
            ]);
        }
        if ($input['starts_at'] >= $input['ends_at']) {
            throw RecommendationException::invalid('Merchandising window is invalid.');
        }
        $rule = RecommendationMerchandisingRule::query()->create([
            'public_id' => (string) Str::uuid(),
            'name' => $input['name'],
            'placement' => RecommendationPlacement::from($input['placement']),
            'product_id' => $input['product_id'] ?? null,
            'product_category_id' => $input['product_category_id'] ?? null,
            'adjustment_type' => $type,
            'adjustment_value' => $value,
            'priority' => $input['priority'] ?? 100,
            'status' => MerchandisingRuleStatus::from($input['status'] ?? 'active'),
            'paid_placement' => (bool) ($input['paid_placement'] ?? false),
            'reason' => $input['reason'],
            'starts_at' => $input['starts_at'],
            'ends_at' => $input['ends_at'],
            'created_by' => $actor->id,
            'approved_by' => $actor->id,
        ]);
        $this->cache->bump();
        $this->audit->record(AuditEvent::RecommendationMerchandisingSaved, $actor, 'recommendation_merchandising_rule', $rule->public_id, null, [
            'type' => $type->value,
            'value' => $value,
            'paid' => $rule->paid_placement,
            'placement' => $rule->placement->value,
        ], $input['reason']);

        return $rule;
    }
}
