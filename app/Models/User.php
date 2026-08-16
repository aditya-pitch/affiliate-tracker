<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_AFFILIATE = 'affiliate';

    public const ROLE_ADMIN = 'admin';

    protected $fillable = [
        'name',
        'email',
        'password',
        'date_of_birth',
        'role',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    // --- Relations -------------------------------------------------------

    public function profile(): HasOne
    {
        return $this->hasOne(AffiliateProfile::class);
    }

    public function couponCodes(): HasMany
    {
        return $this->hasMany(CouponCode::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class);
    }

    // --- Roles -----------------------------------------------------------

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isAffiliate(): bool
    {
        return $this->role === self::ROLE_AFFILIATE;
    }

    // --- Convenience -----------------------------------------------------

    /**
     * The creator's own commission rate as a decimal fraction (0.15 == 15%).
     * Spec section 5.5 is explicit that this must never be hard-coded.
     */
    public function commissionRate(): float
    {
        return (float) $this->profile->commission_rate;
    }

    /**
     * INR for Indian creators, USD for creators abroad (spec section 5.4).
     */
    public function payoutCurrency(): string
    {
        return $this->profile->payout_currency
            ?? config('affiliate.default_payout_currency');
    }

    /**
     * The creator's first name, for the encouragement messages and emails.
     *
     * Called from the shared layout on every page, including the admin pages
     * where the profile is not loaded, so it only consults the profile when
     * that relation is already in memory rather than firing a query per view.
     */
    public function firstName(): string
    {
        $source = $this->relationLoaded('profile')
            ? ($this->profile?->display_name ?: $this->name)
            : $this->name;

        return str($source)->trim()->explode(' ')->first();
    }
}
