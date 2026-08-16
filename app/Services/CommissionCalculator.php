<?php

namespace App\Services;

use App\Support\Money;

/**
 * The commission maths, exactly as specified in section 5.5.
 *
 *     A          = customer payment - GST
 *     commission = A x (the creator's own rate)
 *     fee        = A x 5%
 *     payout     = commission - fee
 *
 * Note the two things that are easy to get wrong here, both called out in the
 * spec: commission is calculated on the value *excluding* GST, not on what the
 * customer paid; and the 5% transaction fee is also calculated on A, not on the
 * commission.
 *
 * Worked example from the spec, which the unit tests assert against:
 * a customer pays 10,000.00; removing 18% GST (1,525.42) leaves 8,474.58;
 * a 15% commission on that is 1,271.19; the 5% fee is 423.73; the payout is
 * 847.46.
 */
final class CommissionCalculator
{
    public function __construct(
        private readonly float $gstRate,
        private readonly float $transactionFeeRate,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            gstRate: (float) config('affiliate.gst_rate', 0.18),
            transactionFeeRate: (float) config('affiliate.transaction_fee_rate', 0.05),
        );
    }

    /**
     * Build the full summary from the totals of a sale.
     *
     * @param  float  $grossEarnings  Total of successful orders, GST inclusive,
     *                                already converted to the creator's payout
     *                                currency at each order's locked rate.
     * @param  float  $commissionRate  The creator's own rate, as a decimal
     *                                 fraction (0.15 == 15%).
     */
    public function summarise(
        string $currency,
        int $unitsSold,
        int $refundedOrders,
        float $grossEarnings,
        float $commissionRate,
    ): CommissionBreakdown {
        // Remove GST to reach the sale value excluding GST -- "A" in the spec.
        // Rounded once, here, so every figure below is derived from the same
        // number the creator sees on the "Net sales" row.
        $netSales = Money::round($grossEarnings / (1 + $this->gstRate));

        $gstAmount = Money::round($grossEarnings - $netSales);

        $commissionAmount = Money::round($netSales * $commissionRate);

        // Also on A, not on the commission (spec section 5.5).
        $transactionFee = Money::round($netSales * $this->transactionFeeRate);

        $payoutAmount = Money::round($commissionAmount - $transactionFee);

        return new CommissionBreakdown(
            currency: $currency,
            unitsSold: $unitsSold,
            refundedOrders: $refundedOrders,
            grossEarnings: Money::round($grossEarnings),
            gstAmount: $gstAmount,
            netSales: $netSales,
            commissionRate: $commissionRate,
            commissionAmount: $commissionAmount,
            transactionFee: $transactionFee,
            payoutAmount: $payoutAmount,
        );
    }

    /**
     * The payout a single order contributes. Used by the per-order
     * notification email, which tells the creator what they just earned.
     *
     * Summing this across orders will not always equal the summary payout to
     * the last paisa -- the summary rounds once on the total, which is the
     * correct figure to pay. This is for "you just earned about X" messaging.
     */
    public function forSingleOrder(float $grossAmount, float $commissionRate): float
    {
        $netSales = Money::round($grossAmount / (1 + $this->gstRate));

        return Money::round(
            ($netSales * $commissionRate) - ($netSales * $this->transactionFeeRate)
        );
    }
}
