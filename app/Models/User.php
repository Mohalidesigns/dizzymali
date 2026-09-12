<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $country_code
 * @property string $preferred_currency
 * @property string $unit_preference
 * @property bool $whatsapp_opt_in
 * @property bool $marketing_opt_in
 * @property-read MeasurementProfile|null $defaultMeasurementProfile
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'country_code',
        'preferred_currency', 'unit_preference',
        'whatsapp_opt_in', 'marketing_opt_in', 'privacy_consented_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'whatsapp_opt_in' => 'boolean',
            'marketing_opt_in' => 'boolean',
            'privacy_consented_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return HasMany<MeasurementProfile, $this> */
    public function measurementProfiles(): HasMany
    {
        return $this->hasMany(MeasurementProfile::class);
    }

    /** @return HasOne<MeasurementProfile, $this> */
    public function defaultMeasurementProfile(): HasOne
    {
        return $this->hasOne(MeasurementProfile::class)->where('is_default', true);
    }

    /** @return HasMany<Address, $this> */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /** @return HasOne<Address, $this> */
    public function defaultAddress(): HasOne
    {
        return $this->hasOne(Address::class)->where('is_default', true);
    }

    public function isBackOffice(): bool
    {
        return $this->hasAnyRole(UserRole::backOffice());
    }

    public function isAdmin(): bool
    {
        return $this->hasAnyRole([UserRole::Admin->value, UserRole::SuperAdmin->value]);
    }

    public function isTailor(): bool
    {
        return $this->hasRole(UserRole::Tailor->value);
    }

    /** The draft the customer is part-way through, if any. */
    public function currentDraft(): ?Order
    {
        return $this->orders()->drafts()->latest()->first();
    }
}
