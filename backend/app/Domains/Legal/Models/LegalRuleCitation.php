<?php

declare(strict_types=1);

namespace App\Domains\Legal\Models;

use App\Domains\Legal\Enums\CitationPurpose;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $legal_rule_id
 * @property int $legal_provision_id
 * @property CitationPurpose $citation_purpose
 * @property string|null $quoted_excerpt
 * @property bool $is_primary
 */
class LegalRuleCitation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'legal_rule_id',
        'legal_provision_id',
        'citation_purpose',
        'quoted_excerpt',
        'citation_note',
        'is_primary',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'citation_purpose' => CitationPurpose::class,
            'is_primary' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<LegalRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(LegalRule::class, 'legal_rule_id');
    }

    /**
     * @return BelongsTo<LegalProvision, $this>
     */
    public function provision(): BelongsTo
    {
        return $this->belongsTo(LegalProvision::class, 'legal_provision_id');
    }
}
