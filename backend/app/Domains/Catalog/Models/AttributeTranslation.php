<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $attribute_id
 * @property string $locale
 * @property string $name
 * @property string|null $description
 */
class AttributeTranslation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'attribute_id',
        'locale',
        'name',
        'description',
    ];

    /**
     * @return BelongsTo<Attribute, $this>
     */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }
}
