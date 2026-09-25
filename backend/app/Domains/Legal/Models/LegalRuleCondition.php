<?php

declare(strict_types=1);

namespace App\Domains\Legal\Models;

use App\Domains\Legal\Enums\ConditionOperator;
use App\Domains\Legal\Enums\ConditionValueType;
use App\Domains\Legal\Enums\LegalConditionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $legal_rule_id
 * @property LegalConditionType $condition_type
 * @property ConditionOperator $operator
 * @property ConditionValueType $value_type
 * @property string|null $string_value
 * @property int|null $integer_value
 * @property string|null $decimal_value
 * @property bool|null $boolean_value
 * @property Carbon|null $date_value
 */
class LegalRuleCondition extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'legal_rule_id',
        'condition_type',
        'operator',
        'value_type',
        'string_value',
        'integer_value',
        'decimal_value',
        'boolean_value',
        'date_value',
        'time_value',
        'reference_type',
        'reference_id',
        'unit_code',
        'group_key',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'condition_type' => LegalConditionType::class,
            'operator' => ConditionOperator::class,
            'value_type' => ConditionValueType::class,
            'boolean_value' => 'boolean',
            'date_value' => 'date',
            'integer_value' => 'integer',
            'sort_order' => 'integer',
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
