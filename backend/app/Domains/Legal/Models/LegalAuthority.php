<?php

declare(strict_types=1);

namespace App\Domains\Legal\Models;

use App\Domains\Legal\Enums\AuthorityType;
use Database\Factories\LegalAuthorityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string $slug
 * @property AuthorityType $authority_type
 * @property string $jurisdiction_code
 * @property bool $is_active
 * @property bool $is_fictional
 */
class LegalAuthority extends Model
{
    /** @use HasFactory<LegalAuthorityFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'name',
        'slug',
        'authority_type',
        'country_code',
        'jurisdiction_code',
        'official_website_url',
        'description',
        'is_active',
        'is_fictional',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'authority_type' => AuthorityType::class,
            'is_active' => 'boolean',
            'is_fictional' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->public_id ??= (string) Str::uuid();
        });
    }

    protected static function newFactory(): LegalAuthorityFactory
    {
        return LegalAuthorityFactory::new();
    }

    /**
     * @return HasMany<LegalSource, $this>
     */
    public function sources(): HasMany
    {
        return $this->hasMany(LegalSource::class);
    }
}
