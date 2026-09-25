<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Models;

use App\Domains\Catalog\Models\MediaAttachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpeciesMediaAttribution extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'media_attachment_id',
        'photographer_or_creator',
        'license',
        'source_url',
        'locale',
    ];

    /**
     * @return BelongsTo<MediaAttachment, $this>
     */
    public function attachment(): BelongsTo
    {
        return $this->belongsTo(MediaAttachment::class, 'media_attachment_id');
    }
}
