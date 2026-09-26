<?php

declare(strict_types=1);

namespace App\Domains\Geography\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $spatial_zone_id
 * @property string $locale
 * @property string $name
 * @property string|null $short_description
 */
class SpatialZoneTranslation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'spatial_zone_id',
        'locale',
        'name',
        'short_description',
    ];

    /**
     * @return BelongsTo<SpatialZone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(SpatialZone::class, 'spatial_zone_id');
    }
}
