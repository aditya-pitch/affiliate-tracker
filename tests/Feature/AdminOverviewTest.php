<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Sale;
use App\Models\User;
use App\Services\AdminSaleOverview;
use App\Services\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The cross-creator overview our team lands on.
 */
class AdminOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_every_creator_on_the_sale_with_their_own_rate(): void
    {
        $sale = Sale::factory()->live()->create();

        $fifteen = $this->makeAffiliate(commissionRate: 0.15, code: 'FIFTEEN');
        $twenty = $this->makeAffiliate(commissionRate: 0.20, code: 'TWENTY');

        foreach ([$fifteen, $twenty] as $creator) {
            Order::factory()->create([
                'sale_id' => $sale->id,
                'user_id' => $creator->id,
                'coupon_code_id' => $creator->couponCodes->first()->id,
                'converted_amount' => 10000,
            ]);
        }

        $data = app(AdminSaleOverview::class)->for($sale);

        $this->assertSame(2, $data['creators']);
        $this->assertSame(2, $data['units']);

        $payouts = collect($data['rows'])->pluck('payout', 'user_id');

        // Same gross, different rates, so the payouts differ -- and match what
        // each creator sees on their own dashboard.
        $this->assertSame(847.46, $payouts[$fifteen->id]);
        $this->assertSame(1271.19, $payouts[$twenty->id]);
    }

    /**
     * Creators are paid in INR or USD depending on where they are (spec 5.4),
     * so totals must never be added into one meaningless number.
     */
    public function test_totals_are_grouped_by_payout_currency_not_summed_together(): void
    {
        $sale = Sale::factory()->live()->create();

        $indian = $this->makeAffiliate(commissionRate: 0.15, payoutCurrency: 'INR', code: 'INRCODE');
        $abroad = $this->makeAffiliate(commissionRate: 0.15, payoutCurrency: 'USD', code: 'USDCODE');

        Order::factory()->create([
            'sale_id' => $sale->id,
            'user_id' => $indian->id,
            'coupon_code_id' => $indian->couponCodes->first()->id,
            'converted_amount' => 10000,
            'payout_currency' => 'INR',
        ]);

        Order::factory()->create([
            'sale_id' => $sale->id,
            'user_id' => $abroad->id,
            'coupon_code_id' => $abroad->couponCodes->first()->id,
            'converted_amount' => 500,
            'payout_currency' => 'USD',
        ]);

        $data = app(AdminSaleOverview::class)->for($sale);

        $this->assertArrayHasKey('INR', $data['totals']);
        $this->assertArrayHasKey('USD', $data['totals']);

        $this->assertSame(847.46, $data['totals']['INR']['payout']);
        $this->assertSame(42.37, $data['totals']['USD']['payout']);

        $this->assertSame(1, $data['totals']['INR']['creators']);
        $this->assertSame(1, $data['totals']['USD']['creators']);
    }

    public function test_refunds_are_counted_but_kept_out_of_the_money(): void
    {
        $sale = Sale::factory()->live()->create();
        $creator = $this->makeAffiliate(commissionRate: 0.15);

        Order::factory()->count(2)->create([
            'sale_id' => $sale->id,
            'user_id' => $creator->id,
            'coupon_code_id' => $creator->couponCodes->first()->id,
            'converted_amount' => 5000,
        ]);

        Order::factory()->refunded()->create([
            'sale_id' => $sale->id,
            'user_id' => $creator->id,
            'coupon_code_id' => $creator->couponCodes->first()->id,
            'converted_amount' => 5000,
        ]);

        $data = app(AdminSaleOverview::class)->for($sale);

        $this->assertSame(2, $data['units']);
        $this->assertSame(1, $data['refunded']);
        $this->assertSame(10000.00, $data['rows'][0]['gross']);
    }

    /**
     * Once a sale is closed out the overview must read the locked snapshots,
     * so our screen and the creator's report cannot disagree.
     */
    public function test_a_closed_sale_reads_the_locked_settlement_figures(): void
    {
        $creator = $this->makeAffiliate(commissionRate: 0.15);
        $sale = Sale::factory()->ended()->create();

        Order::factory()->count(2)->create([
            'sale_id' => $sale->id,
            'user_id' => $creator->id,
            'coupon_code_id' => $creator->couponCodes->first()->id,
            'converted_amount' => 5000,
        ]);

        app(SettlementService::class)->closeSale($sale);

        // A late order that must not move a locked report.
        Order::factory()->create([
            'sale_id' => $sale->id,
            'user_id' => $creator->id,
            'coupon_code_id' => $creator->couponCodes->first()->id,
            'converted_amount' => 99999,
        ]);

        $data = app(AdminSaleOverview::class)->for($sale->fresh());

        $this->assertTrue($data['locked']);
        $this->assertSame(2, $data['units']);
        $this->assertSame(10000.00, $data['rows'][0]['gross']);
    }

    public function test_the_overview_screens_are_closed_to_creators(): void
    {
        $creator = $this->makeAffiliate();
        $sale = Sale::factory()->live()->create();

        $this->actingAs($creator)->get(route('admin.overview.index'))->assertForbidden();
        $this->actingAs($creator)->get(route('admin.overview.show', $sale))->assertForbidden();
        $this->actingAs($creator)->getJson(route('admin.overview.live', $sale))->assertForbidden();
        $this->actingAs($creator)->get(route('admin.overview.download', $sale))->assertForbidden();
    }

    public function test_an_admin_lands_on_the_live_sale(): void
    {
        $creator = $this->makeAffiliate();

        Sale::factory()->closed()->create(['name' => 'Old Sale', 'slug' => 'old-sale']);
        $live = Sale::factory()->live()->create(['name' => 'Running Now', 'slug' => 'running-now']);

        Order::factory()->create([
            'sale_id' => $live->id,
            'user_id' => $creator->id,
            'coupon_code_id' => $creator->couponCodes->first()->id,
        ]);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.overview.index'))
            ->assertRedirect(route('admin.overview.show', $live));
    }
}
