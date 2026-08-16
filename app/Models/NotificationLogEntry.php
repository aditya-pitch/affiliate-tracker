<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLogEntry extends Model
{
    protected $table = 'notification_log';

    public const TYPE_ORDER = 'order';

    public const TYPE_ORDER_DIGEST = 'order_digest';

    public const TYPE_WEEKLY_SUMMARY = 'weekly_summary';

    public const TYPE_SALE_ENDED = 'sale_ended';

    public const TYPE_PAYMENT_CONFIRMED = 'payment_confirmed';

    protected $fillable = [
        'user_id',
        'sale_id',
        'order_id',
        'type',
        'channel',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
