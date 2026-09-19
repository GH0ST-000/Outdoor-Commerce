<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $attribute_value_id
 * @property string $locale
 * @property string $name
 * @property string|null $description
 */
class AttributeValueTranslation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'attribute_value_id',
        'locale',
        'name',
        'description',
    ];

    /**
     * @return BelongsTo<AttributeValue, $this>
     */
    public function attributeValue(): BelongsTo
    {
        return $this->belongsTo(AttributeValue::class);
    }
}
