<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $locale
 * @property string $label
 */
class ContextTaxonomyTermTranslation extends Model
{
    protected $fillable = [
        'context_taxonomy_term_id',
        'locale',
        'label',
        'description',
    ];

    /**
     * @return BelongsTo<ContextTaxonomyTerm, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(ContextTaxonomyTerm::class, 'context_taxonomy_term_id');
    }
}
