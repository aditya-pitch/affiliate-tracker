<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'coupon_code_id',
        'user_id',
        'order_ref',
        'placed_at',
        'customer_first_name',
        'customer_last_name',
        'customer_email',
        'country',
        'state',
        'plugin',
        'currency',
        'amount',
        'payout_currency',
        'exchange_rate',
        'converted_amount',
        'is_refunded',
        'refunded_at',
    ];

    /**
     * The customer's email is never sent to the browser. Hiding it here means
     * a stray toJson() on an order cannot leak it into the polling response.
     */
    protected $hidden = [
        'customer_email',
        'customer_last_name',
    ];

    protected function casts(): array
    {
        return [
            'placed_at' => 'datetime',
            'refunded_at' => 'datetime',
            'amount' => 'decimal:2',
            'converted_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:8',
            'is_refunded' => 'boolean',
        ];
    }

    // --- Relations -------------------------------------------------------

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function couponCode(): BelongsTo
    {
        return $this->belongsTo(CouponCode::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // --- Scopes ----------------------------------------------------------

    /**
     * Spec section 8: refunded orders never count toward gross earnings or
     * commission. Every money query goes through this scope.
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('is_refunded', false);
    }

    public function scopeRefunded(Builder $query): Builder
    {
        return $query->where('is_refunded', true);
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    // --- Masking (spec sections 5.3 / 9) ---------------------------------

    /**
     * "Show only last 2 digits of the ID. Use XX for the remaining digits."
     *
     * PI-2026-0417  ->  XXXXXXXXXX17
     */
    public function maskedOrderRef(): string
    {
        $visible = (int) config('affiliate.masking.order_ref_visible_chars', 2);
        $maskChar = (string) config('affiliate.masking.order_ref_mask_char', 'X');

        $ref = (string) $this->order_ref;
        $length = mb_strlen($ref);

        if ($length <= $visible) {
            return $ref;
        }

        return str_repeat($maskChar, $length - $visible).mb_substr($ref, -$visible);
    }

    /**
     * "Show only the name. The surname should be shown using XX for privacy."
     *
     * Aditi Raghunathan  ->  Aditi XX
     */
    public function maskedCustomerName(): string
    {
        $mask = (string) config('affiliate.masking.surname_mask', 'XX');

        return trim($this->customer_first_name.' '.$mask);
    }
}
