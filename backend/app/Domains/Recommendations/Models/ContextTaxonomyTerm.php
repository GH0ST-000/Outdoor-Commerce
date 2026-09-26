<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Models;

use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\Category;
use App\Domains\Identity\Models\User;
use App\Domains\Recommendations\Enums\ContextDimension;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property ContextDimension $dimension
 * @property string $code
 * @property string $default_label
 * @property bool $is_active
 */
class ContextTaxonomyTerm extends Model
{
    protected $fillable = [
        'public_id',
        'dimension',
        'code',
        'parent_id',
        'default_label',
        'description',
        'is_active',
        'sort_order',
        'catalog_category_id',
        'catalog_attribute_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'dimension' => ContextDimension::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(ContextTaxonomyTermTranslation::class);
    }

    public function catalogCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'catalog_category_id');
    }

    public function catalogAttribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class, 'catalog_attribute_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function label(string $locale): string
    {
        $translations = $this->relationLoaded('translations') ? $this->translations : $this->translations()->get();
        $match = $translations->firstWhere('locale', $locale) ?? $translations->firstWhere('locale', 'ka');

        return $match->label ?? $this->default_label;
    }
}
