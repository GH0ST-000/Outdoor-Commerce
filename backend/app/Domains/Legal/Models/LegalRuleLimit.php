<?php

declare(strict_types=1);

namespace App\Domains\Legal\Models;

use App\Domains\Legal\Enums\LegalLimitType;
use App\Domains\Legal\Enums\LimitAppliesPer;
use App\Domains\Legal\Enums\LimitPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $legal_rule_id
 * @property LegalLimitType $limit_type
 * @property string|null $amount
 * @property LimitPeriod $period
 * @property LimitAppliesPer $applies_per
 */
class LegalRuleLimit extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'legal_rule_id',
        'limit_type',
        'amount',
        'unit',
        'period',
        'minimum_value',
        'maximum_value',
        'measurement_unit',
        'applies_per',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'limit_type' => LegalLimitType::class,
            'period' => LimitPeriod::class,
            'applies_per' => LimitAppliesPer::class,
        ];
    }

    /**
     * @return BelongsTo<LegalRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(LegalRule::class, 'legal_rule_id');
    }
}
