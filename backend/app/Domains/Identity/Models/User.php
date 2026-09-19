<?php

declare(strict_types=1);

namespace App\Domains\Identity\Models;

use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Notifications\ResetCustomerPassword;
use App\Domains\Identity\Notifications\VerifyCustomerEmail;
use App\Domains\Identity\Support\EmailNormalizer;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

/**
 * Account owned by the Identity module.
 *
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string|null $phone
 * @property string|null $preferred_locale
 * @property UserStatus $status
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property Carbon|null $last_login_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Address> $addresses
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $table = 'users';

    /**
     * Mass assignment never exposes `status`, `email_verified_at`, or
     * `last_login_at`: those transition only through Identity actions.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'preferred_locale',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }

    /**
     * Emails are stored in canonical form so lookups never need a function index.
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): string => EmailNormalizer::normalize($value),
        );
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isDisabled(): bool
    {
        return ! $this->isActive();
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    /**
     * @return HasMany<Address, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyCustomerEmail);
    }

    /**
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetCustomerPassword($token));
    }

    /**
     * @return Factory<static>
     */
    protected static function newFactory(): Factory
    {
        /** @var Factory<static> */
        return UserFactory::new();
    }
}
