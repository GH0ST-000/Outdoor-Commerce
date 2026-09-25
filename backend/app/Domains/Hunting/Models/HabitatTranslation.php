<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HabitatTranslation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'habitat_id',
        'locale',
        'name',
        'description',
    ];

    /**
     * @return BelongsTo<Habitat, $this>
     */
    public function habitat(): BelongsTo
    {
        return $this->belongsTo(Habitat::class);
    }
}
