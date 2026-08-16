<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'display_name',
        'commission_rate',
        'payout_currency',
        'country_code',
        'payout_account_name',
        'payout_details',
        'gst_number',
        'pan_number',
        'notify_master',
        'notify_on_sale',
        'notify_weekly_summary',
        'sale_notification_frequency',
        'last_digest_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'commission_rate' => 'decimal:4',
            'notify_master' => 'boolean',
            'notify_on_sale' => 'boolean',
            'notify_weekly_summary' => 'boolean',
            'last_digest_sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Spec section 6.1: "With the master switch off, none of them are sent."
     * Settlement emails do not consult this method at all -- see section 6.2.
     */
    public function wantsActivityEmail(string $type): bool
    {
        if (! $this->notify_master) {
            return false;
        }

        return match ($type) {
            'sale' => (bool) $this->notify_on_sale,
            'weekly_summary' => (bool) $this->notify_weekly_summary,
            default => false,
        };
    }

    /**
     * The creator's commission rate rendered for display, e.g. "15%" or
     * "12.5%" -- trailing zeros trimmed so whole rates stay clean.
     */
    public function commissionRatePercent(): string
    {
        $percent = (float) $this->commission_rate * 100;

        return rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.').'%';
    }
}
