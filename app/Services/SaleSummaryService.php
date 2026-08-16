<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Sale;
use App\Models\Settlement;
use App\Models\User;

/**
 * Produces the per-sale summary block (spec section 5.2) for one creator.
 *
 * There are two paths on purpose:
 *
 *  - While a sale is running, figures are calculated live from the orders
 *    table so the dashboard moves as orders come in (section 5.6).
 *  - Once a sale is closed out, the figures come from the settlement snapshot
 *    instead. Section 5.7: "the report is locked -- the figures, the Excel
 *    download and the invoice all refer to the same final numbers."
 */
final class SaleSummaryService
{
    public function __construct(
        private readonly CommissionCalculator $calculator,
    ) {}

    public function for(User $user, Sale $sale): CommissionBreakdown
    {
        $settlement = $sale->isClosedOut()
            ? Settlement::where('sale_id', $sale->id)->where('user_id', $user->id)->first()
            : null;

        return $settlement
            ? $this->fromSettlement($settlement)
            : $this->calculateLive($user, $sale);
    }

    /**
     * Live figures, straight off the orders table.
     */
    public function calculateLive(User $user, Sale $sale): CommissionBreakdown
    {
        /*
         | Deliberately an Eloquent Builder rather than $user->orders(), because
         | only Builder defines __clone -- cloning a relation would share the
         | underlying query, and the refunded filter below would leak into the
         | successful-orders count.
         */
        $base = Order::query()
            ->where('user_id', $user->id)
            ->where('sale_id', $sale->id);

        // Refunded orders are excluded from every money figure and counted
        // only in their own total (spec section 8).
        $unitsSold = (clone $base)->where('is_refunded', false)->count();
        $grossEarnings = (float) (clone $base)->where('is_refunded', false)->sum('converted_amount');
        $refundedOrders = (clone $base)->where('is_refunded', true)->count();

        return $this->calculator->summarise(
            currency: $user->payoutCurrency(),
            unitsSold: $unitsSold,
            refundedOrders: $refundedOrders,
            grossEarnings: $grossEarnings,
            commissionRate: $user->commissionRate(),
        );
    }

    /**
     * Locked figures, read back off the settlement snapshot.
     */
    public function fromSettlement(Settlement $settlement): CommissionBreakdown
    {
        return new CommissionBreakdown(
            currency: $settlement->currency,
            unitsSold: (int) $settlement->units_sold,
            refundedOrders: (int) $settlement->refunded_orders,
            grossEarnings: (float) $settlement->gross_earnings,
            gstAmount: (float) $settlement->gst_amount,
            netSales: (float) $settlement->net_sales,
            commissionRate: (float) $settlement->commission_rate,
            commissionAmount: (float) $settlement->commission_amount,
            transactionFee: (float) $settlement->transaction_fee,
            payoutAmount: (float) $settlement->payout_amount,
        );
    }
}
