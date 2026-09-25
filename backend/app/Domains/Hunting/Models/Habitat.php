<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $code
 */
class Habitat extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<HabitatTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(HabitatTranslation::class);
    }

    public function localizedName(string $locale): string
    {
        $translations = $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->get();

        $match = $translations->firstWhere('locale', $locale);
        if ($match instanceof HabitatTranslation) {
            return $match->name;
        }

        $first = $translations->first();

        return $first instanceof HabitatTranslation ? $first->name : $this->code;
    }
}
