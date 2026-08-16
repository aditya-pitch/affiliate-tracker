<?php

namespace Tests\Unit;

use App\Services\CommissionCalculator;
use App\Support\Money;
use Tests\TestCase;

/**
 * The commission maths from spec section 5.5.
 *
 * The first test is the spec's own worked example, figure for figure. If that
 * one ever goes red, the dashboard is paying creators the wrong amount.
 */
class CommissionCalculatorTest extends TestCase
{
    private CommissionCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new CommissionCalculator(gstRate: 0.18, transactionFeeRate: 0.05);
    }

    public function test_it_matches_the_worked_example_from_the_spec(): void
    {
        // "a customer pays ₹10,000.00; removing 18% GST (₹1,525.42) leaves a
        //  sale value excluding GST of ₹8,474.58; the 15% commission on that is
        //  ₹1,271.19; the 5% transaction fee is ₹423.73; so, the final
        //  affiliate payout is ₹847.46."
        $summary = $this->calculator->summarise(
            currency: 'INR',
            unitsSold: 1,
            refundedOrders: 0,
            grossEarnings: 10000.00,
            commissionRate: 0.15,
        );

        $this->assertSame(10000.00, $summary->grossEarnings);
        $this->assertSame(1525.42, $summary->gstAmount);
        $this->assertSame(8474.58, $summary->netSales);
        $this->assertSame(1271.19, $summary->commissionAmount);
        $this->assertSame(423.73, $summary->transactionFee);
        $this->assertSame(847.46, $summary->payoutAmount);
    }

    public function test_commission_is_calculated_on_the_value_excluding_gst_not_on_what_the_customer_paid(): void
    {
        $summary = $this->calculator->summarise(
            currency: 'INR',
            unitsSold: 1,
            refundedOrders: 0,
            grossEarnings: 10000.00,
            commissionRate: 0.15,
        );

        // The mistake this guards against: 15% of the ₹10,000 the customer paid.
        $this->assertNotSame(1500.00, $summary->commissionAmount);
        $this->assertSame(1271.19, $summary->commissionAmount);
    }

    public function test_the_transaction_fee_is_charged_on_net_sales_not_on_the_commission(): void
    {
        $summary = $this->calculator->summarise(
            currency: 'INR',
            unitsSold: 1,
            refundedOrders: 0,
            grossEarnings: 10000.00,
            commissionRate: 0.15,
        );

        // 5% of the commission would be ₹63.56 — a very different payout.
        $this->assertSame(423.73, $summary->transactionFee);
        $this->assertSame(Money::round($summary->netSales * 0.05), $summary->transactionFee);
    }

    /**
     * Spec section 5.5: "the dashboard must use each creator's own rate rather
     * than a single hard-coded value."
     */
    public function test_it_uses_each_creators_own_rate(): void
    {
        $atTwenty = $this->calculator->summarise('INR', 1, 0, 10000.00, 0.20);
        $atTwelveAndAHalf = $this->calculator->summarise('INR', 1, 0, 10000.00, 0.125);

        $this->assertSame(1694.92, $atTwenty->commissionAmount);
        $this->assertSame(1059.32, $atTwelveAndAHalf->commissionAmount);

        // Same gross, same fee, different payout — because the rate differs.
        $this->assertSame(423.73, $atTwenty->transactionFee);
        $this->assertSame(423.73, $atTwelveAndAHalf->transactionFee);
        $this->assertSame(1271.19, $atTwenty->payoutAmount);
        $this->assertSame(635.59, $atTwelveAndAHalf->payoutAmount);
    }

    public function test_a_sale_with_no_orders_produces_zeroes_rather_than_a_division_error(): void
    {
        $summary = $this->calculator->summarise('INR', 0, 0, 0.0, 0.15);

        $this->assertSame(0.0, $summary->grossEarnings);
        $this->assertSame(0.0, $summary->netSales);
        $this->assertSame(0.0, $summary->payoutAmount);
    }

    public function test_refunded_orders_are_reported_separately_from_the_money(): void
    {
        $summary = $this->calculator->summarise('INR', 33, 2, 207430.20, 0.15);

        $this->assertSame(33, $summary->unitsSold);
        $this->assertSame(2, $summary->refundedOrders);

        // The refunds are a count only; they never touch the gross.
        $this->assertSame(207430.20, $summary->grossEarnings);
    }

    public function test_the_summary_rows_are_in_the_order_the_spec_lists_them(): void
    {
        $rows = $this->calculator->summarise('INR', 1, 0, 10000.00, 0.15)->rows();

        $labels = array_column($rows, 'label');

        $this->assertCount(8, $rows);
        $this->assertSame('No. of units sold', $labels[0]);
        $this->assertSame('Refunded orders', $labels[1]);
        $this->assertSame('Gross earnings (GST inclusive)', $labels[2]);
        $this->assertSame('Less: GST @ 18%', $labels[3]);
        $this->assertSame('Net sales (excluding GST)', $labels[4]);
        $this->assertSame('Affiliate commission @ 15%', $labels[5]);
        $this->assertSame('Less: transaction fees @ 5%', $labels[6]);
        $this->assertSame('Affiliate payout', $labels[7]);

        // The payout is the row that gets emphasised.
        $this->assertTrue($rows[7]['emphasis']);
    }
}
