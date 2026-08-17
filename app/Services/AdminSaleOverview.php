<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Sale;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The cross-creator view of a single sale, for the internal overview screen.
 *
 * This is the admin counterpart to SaleSummaryService: same figures, same
 * commission rules, but one row per creator instead of one summary for one
 * creator. It follows the same live/locked split -- while a sale runs the rows
 * are calculated from the orders table, and once it has been closed out they
 * come from the settlement snapshots so the admin sees exactly the numbers the
 * creators were sent.
 *
 * On totals: creators are paid in different currencies (spec 5.4 -- INR for
 * Indian creators, USD for those abroad), so there is deliberately no single
 * grand total. Adding rupees to dollars would produce a number that looks
 * authoritative and means nothing. Totals are grouped by payout currency.
 */
final class AdminSaleOverview
{
    public function __construct(
        private readonly CommissionCalculator $calculator,
    ) {}

    /**
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     totals: array<string, array<string, float|int>>,
     *     creators: int,
     *     units: int,
     *     refunded: int
     * }
     */
    public function for(Sale $sale): array
    {
        $locked = $sale->isClosedOut();

        $rows = $locked
            ? $this->rowsFromSettlements($sale)
            : $this->rowsFromOrders($sale);

        // Biggest earners first -- within a currency, since comparing across
        // currencies would order the list by exchange rate rather than merit.
        $rows = $rows->sortBy([
            ['currency', 'asc'],
            ['payout', 'desc'],
        ])->values()->all();

        return [
            'rows' => $rows,
            'totals' => $this->totalsByCurrency($rows),
            'creators' => count($rows),
            'units' => array_sum(array_column($rows, 'units')),
            'refunded' => array_sum(array_column($rows, 'refunded')),
            'locked' => $locked,
        ];
    }

    /**
     * Live figures, straight off the orders table.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function rowsFromOrders(Sale $sale): Collection
    {
        /*
         | One grouped query for the money rather than a query per creator --
         | an overview of a busy sale should not cost one round trip per
         | affiliate on the campaign.
         */
        $aggregates = Order::query()
            ->selectRaw('user_id')
            ->selectRaw('SUM(CASE WHEN is_refunded = 0 THEN 1 ELSE 0 END) as units')
            ->selectRaw('SUM(CASE WHEN is_refunded = 1 THEN 1 ELSE 0 END) as refunded')
            ->selectRaw('SUM(CASE WHEN is_refunded = 0 THEN converted_amount ELSE 0 END) as gross')
            ->where('sale_id', $sale->id)
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $users = User::with('profile', 'couponCodes')
            ->whereIn('id', $aggregates->keys())
            ->get();

        $codesUsed = $this->codesUsedPerCreator($sale);

        return $users->map(function (User $user) use ($aggregates, $codesUsed, $sale) {
            $row = $aggregates[$user->id];

            $summary = $this->calculator->summarise(
                currency: $user->payoutCurrency(),
                unitsSold: (int) $row->units,
                refundedOrders: (int) $row->refunded,
                grossEarnings: (float) $row->gross,
                commissionRate: $user->commissionRate(),
            );

            return $this->row($user, $summary, $codesUsed[$user->id] ?? [], null, $sale);
        });
    }

    /**
     * Locked figures, read back off the settlement snapshots.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function rowsFromSettlements(Sale $sale): Collection
    {
        $settlements = Settlement::with('user.profile', 'user.couponCodes')
            ->where('sale_id', $sale->id)
            ->get();

        $codesUsed = $this->codesUsedPerCreator($sale);

        return $settlements->map(function (Settlement $settlement) use ($codesUsed, $sale) {
            $summary = new CommissionBreakdown(
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

            return $this->row(
                $settlement->user,
                $summary,
                $codesUsed[$settlement->user_id] ?? [],
                $settlement,
                $sale
            );
        });
    }

    /**
     * @param  list<string>  $codes
     * @return array<string, mixed>
     */
    private function row(
        User $user,
        CommissionBreakdown $summary,
        array $codes,
        ?Settlement $settlement,
        Sale $sale,
    ): array {
        return [
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'codes' => $codes ?: $user->couponCodes->pluck('code')->all(),
            'currency' => $summary->currency,
            'units' => $summary->unitsSold,
            'refunded' => $summary->refundedOrders,
            'gross' => $summary->grossEarnings,
            'rate' => $summary->commissionRate,
            'commission' => $summary->commissionAmount,
            'payout' => $summary->payoutAmount,
            'settlement' => $settlement,
            'status' => $settlement?->stageLabel() ?? ($sale->hasEnded() ? 'Awaiting close-out' : 'Live'),
            'summary' => $summary,
        ];
    }

    /**
     * Which of a creator's codes were actually used on this sale, so the
     * overview shows the codes in play rather than every code they own.
     *
     * @return array<int, list<string>>
     */
    private function codesUsedPerCreator(Sale $sale): array
    {
        $pairs = Order::query()
            ->join('coupon_codes', 'coupon_codes.id', '=', 'orders.coupon_code_id')
            ->where('orders.sale_id', $sale->id)
            ->distinct()
            ->get(['orders.user_id', 'coupon_codes.code']);

        $grouped = [];

        foreach ($pairs as $pair) {
            $grouped[$pair->user_id][] = $pair->code;
        }

        foreach ($grouped as $userId => $codes) {
            $grouped[$userId] = array_values(array_unique($codes));
            sort($grouped[$userId]);
        }

        return $grouped;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, array<string, float|int>>
     */
    private function totalsByCurrency(array $rows): array
    {
        $totals = [];

        foreach ($rows as $row) {
            $currency = $row['currency'];

            $totals[$currency] ??= [
                'creators' => 0,
                'units' => 0,
                'refunded' => 0,
                'gross' => 0.0,
                'commission' => 0.0,
                'payout' => 0.0,
            ];

            $totals[$currency]['creators']++;
            $totals[$currency]['units'] += $row['units'];
            $totals[$currency]['refunded'] += $row['refunded'];
            $totals[$currency]['gross'] += $row['gross'];
            $totals[$currency]['commission'] += $row['commission'];
            $totals[$currency]['payout'] += $row['payout'];
        }

        ksort($totals);

        return $totals;
    }
}
