<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class KnowledgeCitation extends Model
{
    /**
     * @var list<string>
     */
    protected $hidden = [
        'editor_note',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'source_id',
        'citable_type',
        'citable_id',
        'claim_key',
        'page_reference',
        'section_reference',
        'quotation_excerpt',
        'editor_note',
    ];

    /**
     * @return BelongsTo<KnowledgeSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSource::class, 'source_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function citable(): MorphTo
    {
        return $this->morphTo();
    }
}
