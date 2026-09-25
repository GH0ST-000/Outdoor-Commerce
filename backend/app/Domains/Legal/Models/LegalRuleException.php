<?php

declare(strict_types=1);

namespace App\Domains\Legal\Models;

use App\Domains\Legal\Enums\ExceptionRelationshipType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $base_rule_id
 * @property int $exception_rule_id
 * @property ExceptionRelationshipType $relationship_type
 * @property int $precedence
 */
class LegalRuleException extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'base_rule_id',
        'exception_rule_id',
        'relationship_type',
        'precedence',
        'reviewed_by',
        'reviewed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'relationship_type' => ExceptionRelationshipType::class,
            'precedence' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<LegalRule, $this>
     */
    public function baseRule(): BelongsTo
    {
        return $this->belongsTo(LegalRule::class, 'base_rule_id');
    }

    /**
     * @return BelongsTo<LegalRule, $this>
     */
    public function exceptionRule(): BelongsTo
    {
        return $this->belongsTo(LegalRule::class, 'exception_rule_id');
    }
}
