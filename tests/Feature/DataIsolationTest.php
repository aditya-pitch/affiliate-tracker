<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Sale;
use App\Models\Settlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spec section 9: "Creators can only access their own dashboard and data."
 *
 * This is the test that matters most for trust. Everything else on the
 * dashboard is a number being wrong; this one is a creator seeing another
 * creator's earnings.
 */
class DataIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_creator_cannot_open_a_sale_they_took_no_part_in(): void
    {
        $mine = $this->makeAffiliate(code: 'MINE');
        $theirs = $this->makeAffiliate(code: 'THEIRS');

        $sale = Sale::factory()->live()->create();

        // Only the other creator has orders on this sale.
        Order::factory()->create([
            'sale_id' => $sale->id,
            'user_id' => $theirs->id,
            'coupon_code_id' => $theirs->couponCodes->first()->id,
        ]);

        $this->actingAs($mine)
            ->get(route('sales.show', $sale))
            ->assertNotFound();
    }

    public function test_the_orders_table_never_includes_another_creators_orders(): void
    {
        $mine = $this->makeAffiliate(code: 'MINE');
        $theirs = $this->makeAffiliate(code: 'THEIRS');

        $sale = Sale::factory()->live()->create();

        Order::factory()->create([
            'sale_id' => $sale->id,
            'user_id' => $mine->id,
            'coupon_code_id' => $mine->couponCodes->first()->id,
            'plugin' => 'Loop2Kit',
            'converted_amount' => 1000,
        ]);

        Order::factory()->create([
            'sale_id' => $sale->id,
            'user_id' => $theirs->id,
            'coupon_code_id' => $theirs->couponCodes->first()->id,
            'plugin' => 'Sonic Atlas',
            'converted_amount' => 5000,
        ]);

        $response = $this->actingAs($mine)->get(route('sales.show', $sale));

        $response->assertOk();
        $response->assertSee('MINE');
        $response->assertDontSee('THEIRS');

        // One order counted, and only their own gross in the summary.
        $response->assertSee('Loop2Kit');
        $response->assertDontSee('Sonic Atlas');
    }

    public function test_the_polling_endpoint_is_scoped_the_same_way_as_the_page(): void
    {
        $mine = $this->makeAffiliate(code: 'MINE');
        $theirs = $this->makeAffiliate(code: 'THEIRS');

        $sale = Sale::factory()->live()->create();

        Order::factory()->create([
            'sale_id' => $sale->id,
            'user_id' => $theirs->id,
            'coupon_code_id' => $theirs->couponCodes->first()->id,
        ]);

        $this->actingAs($mine)
            ->getJson(route('dashboard.live', $sale))
            ->assertNotFound();
    }

    public function test_a_creator_cannot_download_another_creators_report(): void
    {
        $mine = $this->makeAffiliate(code: 'MINE');
        $theirs = $this->makeAffiliate(code: 'THEIRS');

        $sale = Sale::factory()->closed()->create();

        Order::factory()->create([
            'sale_id' => $sale->id,
            'user_id' => $theirs->id,
            'coupon_code_id' => $theirs->couponCodes->first()->id,
        ]);

        $this->actingAs($mine)
            ->get(route('sales.report', $sale))
            ->assertNotFound();
    }

    /**
     * Spec section 5.7: recording a payment is restricted to authorised team
     * members.
     */
    public function test_a_creator_cannot_reach_the_internal_settlement_screen(): void
    {
        $affiliate = $this->makeAffiliate();

        $this->actingAs($affiliate)
            ->get(route('admin.settlements.index'))
            ->assertForbidden();
    }

    public function test_a_creator_cannot_mark_their_own_commission_as_paid(): void
    {
        $affiliate = $this->makeAffiliate();
        $sale = Sale::factory()->closed()->create();

        $settlement = Settlement::create([
            'sale_id' => $sale->id,
            'user_id' => $affiliate->id,
            'currency' => 'INR',
            'commission_rate' => 0.15,
            'payout_amount' => 5000,
        ]);

        $this->actingAs($affiliate)
            ->post(route('admin.settlements.pay', $settlement), [
                'paid_amount' => 5000,
                'paid_on' => now()->toDateString(),
            ])
            ->assertForbidden();

        $this->assertSame(Settlement::STATUS_PENDING, $settlement->fresh()->status);
    }

    public function test_signed_out_visitors_are_sent_to_the_sign_in_page(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('settings'))->assertRedirect(route('login'));
    }
}
