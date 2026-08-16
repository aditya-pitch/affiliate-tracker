<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Sale extends Model
{
    use HasFactory;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_LIVE = 'live';

    public const STATUS_ENDED = 'ended';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'starts_at',
        'ends_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class);
    }

    // --- Status ----------------------------------------------------------

    /**
     * Spec section 5.1: a running sale is marked live, a finished one ended.
     */
    public function status(): string
    {
        $now = Carbon::now();

        if ($this->closed_at !== null || $now->greaterThan($this->ends_at)) {
            return self::STATUS_ENDED;
        }

        if ($now->lessThan($this->starts_at)) {
            return self::STATUS_SCHEDULED;
        }

        return self::STATUS_LIVE;
    }

    public function isLive(): bool
    {
        return $this->status() === self::STATUS_LIVE;
    }

    public function hasEnded(): bool
    {
        return $this->status() === self::STATUS_ENDED;
    }

    /**
     * Whether the settlement snapshots have been written for this sale.
     *
     * A sale can be past its end date without having been closed out yet --
     * the scheduler runs every minute, not continuously. Reports are only
     * locked (spec section 5.7) once this is true.
     */
    public function isClosedOut(): bool
    {
        return $this->closed_at !== null;
    }

    // --- Scopes ----------------------------------------------------------

    public function scopeLive(Builder $query): Builder
    {
        return $query->whereNull('closed_at')
            ->where('starts_at', '<=', Carbon::now())
            ->where('ends_at', '>=', Carbon::now());
    }

    public function scopeEnded(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereNotNull('closed_at')
            ->orWhere('ends_at', '<', Carbon::now()));
    }

    /**
     * Sales a given creator actually took part in, newest first. A creator
     * only ever sees sales their own codes were used in (spec section 9).
     */
    public function scopeForAffiliate(Builder $query, User $user): Builder
    {
        return $query->whereHas('orders', fn (Builder $q) => $q->where('user_id', $user->id));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
