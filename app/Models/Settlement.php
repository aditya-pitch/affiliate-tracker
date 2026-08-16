<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Settlement extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_INVOICE_UPLOADED = 'invoice_uploaded';

    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'sale_id',
        'user_id',
        'status',
        'currency',
        'units_sold',
        'refunded_orders',
        'gross_earnings',
        'gst_amount',
        'net_sales',
        'commission_rate',
        'commission_amount',
        'transaction_fee',
        'payout_amount',
        'finalised_at',
        'invoice_path',
        'invoice_original_name',
        'invoice_size',
        'invoice_uploaded_at',
        'paid_amount',
        'paid_on',
        'payment_reference',
        'paid_by_user_id',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'gross_earnings' => 'decimal:2',
            'gst_amount' => 'decimal:2',
            'net_sales' => 'decimal:2',
            'commission_rate' => 'decimal:4',
            'commission_amount' => 'decimal:2',
            'transaction_fee' => 'decimal:2',
            'payout_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'finalised_at' => 'datetime',
            'invoice_uploaded_at' => 'datetime',
            'paid_at' => 'datetime',
            'paid_on' => 'date',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }

    // --- Stage (spec section 8) ------------------------------------------

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function hasInvoice(): bool
    {
        return $this->invoice_path !== null;
    }

    /**
     * The label the creator sees against the sale: Ended, Invoice uploaded, or
     * Paid. A sale that is still running never has a settlement row at all.
     */
    public function stageLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PAID => 'Paid',
            self::STATUS_INVOICE_UPLOADED => 'Invoice uploaded',
            default => 'Awaiting your invoice',
        };
    }
}
