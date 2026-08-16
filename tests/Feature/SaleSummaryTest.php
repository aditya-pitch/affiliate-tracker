<?php

namespace Tests\Feature;

use App\Models\CouponCode;
use App\Models\Order;
use App\Models\Sale;
use App\Services\SaleSummaryService;
use App\Support\ExchangeRates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleSummaryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Spec section 8: "Refunded orders never count toward gross earnings or
     * commission; they appear only in the Refunded orders total."
     */
    public function test_refunded_orders_are_excluded_from_the_money_but_counted(): void
    {
        $affiliate = $this->makeAffiliate(commissionRate: 0.15);
        $sale = Sale::factory()->live()->create();
        $code = $affiliate->couponCodes->first();

        Order::factory()->count(3)->create([
            'sale_id' => $sale->id,
            'user_id' => $affiliate->id,
            'coupon_code_id' => $code->id,
            'amount' => 1000,
            'converted_amount' => 1000,
        ]);

        Order::factory()->refunded()->create([
            'sale_id' => $sale->id,
            'user_id' => $affiliate->id,
            'coupon_code_id' => $code->id,
            'amount' => 1000,
            'converted_amount' => 1000,
        ]);

        $summary = app(SaleSummaryService::class)->for($affiliate, $sale);

        $this->assertSame(3, $summary->unitsSold);
        $this->assertSame(1, $summary->refundedOrders);

        // Three orders, not four.
        $this->assertSame(3000.00, $summary->grossEarnings);
    }

    /**
     * Spec section 8, confirmed during spec review: "all of a creator's codes
     * roll up into their totals."
     */
    public function test_all_of_a_creators_coupon_codes_roll_up_into_one_set_of_totals(): void
    {
        $affiliate = $this->makeAffiliate(code: 'FIRSTCODE');
        $second = CouponCode::factory()->create([
            'user_id' => $affiliate->id,
            'code' => 'SECONDCODE',
        ]);

        $sale = Sale::factory()->live()->create();

        Order::factory()->count(2)->create([
            'sale_id' => $sale->id,
            'user_id' => $affiliate->id,
            'coupon_code_id' => $affiliate->couponCodes->first()->id,
            'converted_amount' => 1000,
        ]);

        Order::factory()->count(3)->create([
            'sale_id' => $sale->id,
            'user_id' => $affiliate->id,
            'coupon_code_id' => $second->id,
            'converted_amount' => 1000,
        ]);

        $summary = app(SaleSummaryService::class)->for($affiliate, $sale);

        $this->assertSame(5, $summary->unitsSold);
        $this->assertSame(5000.00, $summary->grossEarnings);
    }

    /**
     * Spec section 5.4: order rows keep the currency the customer paid in, but
     * the summary is converted — to INR for Indian creators, USD for creators
     * abroad — at the rate locked when the order was placed.
     */
    public function test_the_summary_converts_multi_currency_orders_at_the_locked_rate(): void
    {
        $affiliate = $this->makeAffiliate(payoutCurrency: 'INR');
        $sale = Sale::factory()->live()->create();
        $code = $affiliate->couponCodes->first();

        // One order in INR, one in USD.
        Order::factory()->create([
            'sale_id' => $sale->id,
            'user_id' => $affiliate->id,
            'coupon_code_id' => $code->id,
            'currency' => 'INR',
            'amount' => 2999,
            'payout_currency' => 'INR',
            'exchange_rate' => 1,
            'converted_amount' => 2999,
        ]);

        $usdRate = ExchangeRates::rate('USD', 'INR');

        Order::factory()->paidIn('USD', 49.00, 'INR')->create([
            'sale_id' => $sale->id,
            'user_id' => $affiliate->id,
            'coupon_code_id' => $code->id,
        ]);

        $summary = app(SaleSummaryService::class)->for($affiliate, $sale);

        $this->assertSame('INR', $summary->currency);
        $this->assertSame(round(2999 + (49.00 * $usdRate), 2), $summary->grossEarnings);
    }

    /**
     * The rate is stored on the order, so a later change to the rate table
     * must not move a historical total (spec sections 5.4 / 8).
     */
    public function test_a_stored_order_keeps_its_rate_regardless_of_later_rates(): void
    {
        $affiliate = $this->makeAffiliate(payoutCurrency: 'INR');
        $sale = Sale::factory()->live()->create();

        $order = Order::factory()->create([
            'sale_id' => $sale->id,
            'user_id' => $affiliate->id,
            'coupon_code_id' => $affiliate->couponCodes->first()->id,
            'currency' => 'USD',
            'amount' => 100,
            'payout_currency' => 'INR',
            'exchange_rate' => 80.00,      // the rate on the day
            'converted_amount' => 8000.00,
        ]);

        $summary = app(SaleSummaryService::class)->for($affiliate, $sale);

        // 8,000 at the locked rate, not 8,750 at today's table rate.
        $this->assertSame(8000.00, $summary->grossEarnings);
        $this->assertSame('80.00000000', $order->fresh()->exchange_rate);
    }

    public function test_creators_on_different_rates_get_different_payouts_for_the_same_sale(): void
    {
        $sale = Sale::factory()->live()->create();

        $fifteen = $this->makeAffiliate(commissionRate: 0.15, code: 'FIFTEEN');
        $twenty = $this->makeAffiliate(commissionRate: 0.20, code: 'TWENTY');

        foreach ([$fifteen, $twenty] as $affiliate) {
            Order::factory()->create([
                'sale_id' => $sale->id,
                'user_id' => $affiliate->id,
                'coupon_code_id' => $affiliate->couponCodes->first()->id,
                'converted_amount' => 10000,
            ]);
        }

        $summaries = app(SaleSummaryService::class);

        $this->assertSame(847.46, $summaries->for($fifteen, $sale)->payoutAmount);
        $this->assertSame(1271.19, $summaries->for($twenty, $sale)->payoutAmount);
    }
}
