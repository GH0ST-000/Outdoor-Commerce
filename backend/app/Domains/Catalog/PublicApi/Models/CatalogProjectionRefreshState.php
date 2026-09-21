<?php

declare(strict_types=1);

namespace App\Domains\Catalog\PublicApi\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $name
 * @property Carbon|null $last_ran_at
 * @property Carbon|null $last_boundary_at
 * @property int $refreshed_variants
 */
class CatalogProjectionRefreshState extends Model
{
    protected $table = 'catalog_projection_refresh_states';

    protected $primaryKey = 'name';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'last_ran_at',
        'last_boundary_at',
        'refreshed_variants',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_ran_at' => 'datetime',
            'last_boundary_at' => 'datetime',
            'refreshed_variants' => 'integer',
        ];
    }
}
