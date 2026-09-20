<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Models;

use Database\Factories\MediaAttachmentTranslationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $media_attachment_id
 * @property string $locale
 * @property string|null $alt_text
 * @property string|null $caption
 */
class MediaAttachmentTranslation extends Model
{
    /** @use HasFactory<MediaAttachmentTranslationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'media_attachment_id',
        'locale',
        'alt_text',
        'caption',
    ];

    /**
     * @return BelongsTo<MediaAttachment, $this>
     */
    public function attachment(): BelongsTo
    {
        return $this->belongsTo(MediaAttachment::class, 'media_attachment_id');
    }

    protected static function newFactory(): MediaAttachmentTranslationFactory
    {
        return MediaAttachmentTranslationFactory::new();
    }
}
