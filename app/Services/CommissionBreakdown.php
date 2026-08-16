<?php

namespace App\Services;

use App\Support\Money;

/**
 * The eight figures that make up the summary block in spec section 5.2, in the
 * order they are displayed.
 */
final class CommissionBreakdown
{
    public function __construct(
        public readonly string $currency,
        public readonly int $unitsSold,
        public readonly int $refundedOrders,
        public readonly float $grossEarnings,     // GST inclusive
        public readonly float $gstAmount,         // less GST @ 18%
        public readonly float $netSales,          // "A" in spec 5.5
        public readonly float $commissionRate,    // the creator's own rate
        public readonly float $commissionAmount,  // A x rate
        public readonly float $transactionFee,    // less transaction fees @ 5%
        public readonly float $payoutAmount,      // final affiliate payout
    ) {}

    /**
     * The summary as label/value rows, ready for the dashboard table, the
     * Excel export and the polling response. Keeping one source for this means
     * the on-screen report and the downloaded report can never drift apart.
     *
     * @return array<int, array{label: string, value: string, emphasis: bool, muted: bool}>
     */
    public function rows(): array
    {
        $gstPercent = self::asPercent((float) config('affiliate.gst_rate', 0.18));
        $feePercent = self::asPercent((float) config('affiliate.transaction_fee_rate', 0.05));
        $ratePercent = self::asPercent($this->commissionRate);

        return [
            ['label' => 'No. of units sold', 'value' => (string) $this->unitsSold, 'emphasis' => false, 'muted' => false],
            ['label' => 'Refunded orders', 'value' => (string) $this->refundedOrders, 'emphasis' => false, 'muted' => false],
            ['label' => 'Gross earnings (GST inclusive)', 'value' => $this->money($this->grossEarnings), 'emphasis' => false, 'muted' => false],
            ['label' => "Less: GST @ {$gstPercent}", 'value' => '- '.$this->money($this->gstAmount), 'emphasis' => false, 'muted' => true],
            ['label' => 'Net sales (excluding GST)', 'value' => $this->money($this->netSales), 'emphasis' => false, 'muted' => false],
            ['label' => "Affiliate commission @ {$ratePercent}", 'value' => $this->money($this->commissionAmount), 'emphasis' => false, 'muted' => false],
            ['label' => "Less: transaction fees @ {$feePercent}", 'value' => '- '.$this->money($this->transactionFee), 'emphasis' => false, 'muted' => true],
            ['label' => 'Affiliate payout', 'value' => $this->money($this->payoutAmount), 'emphasis' => true, 'muted' => false],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'currency' => $this->currency,
            'units_sold' => $this->unitsSold,
            'refunded_orders' => $this->refundedOrders,
            'gross_earnings' => $this->grossEarnings,
            'gst_amount' => $this->gstAmount,
            'net_sales' => $this->netSales,
            'commission_rate' => $this->commissionRate,
            'commission_amount' => $this->commissionAmount,
            'transaction_fee' => $this->transactionFee,
            'payout_amount' => $this->payoutAmount,
            'rows' => $this->rows(),
        ];
    }

    public function money(float $amount): string
    {
        return Money::format($amount, $this->currency);
    }

    /**
     * 0.15 -> "15%", 0.125 -> "12.5%"
     */
    private static function asPercent(float $rate): string
    {
        $percent = $rate * 100;

        return rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.').'%';
    }
}
