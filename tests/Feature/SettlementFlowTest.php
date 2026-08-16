<?php

namespace Tests\Feature;

use App\Mail\PaymentConfirmationMail;
use App\Mail\SaleEndedMail;
use App\Models\Order;
use App\Models\Sale;
use App\Models\Settlement;
use App\Models\User;
use App\Services\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Spec section 5.7 — what happens between a sale ending and a creator getting
 * paid.
 */
class SettlementFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The rule that has a real cost if it breaks: an invoice arriving mid-sale
     * for figures that are still moving.
     *
     * "The 'Upload invoice' option is only active after a sale has ended; it
     * stays disabled while the sale is live."
     */
    public function test_an_invoice_cannot_be_uploaded_while_the_sale_is_still_running(): void
    {
        Storage::fake('invoices');

        $affiliate = $this->makeAffiliate();
        $sale = Sale::factory()->live()->create();

        Order::factory()->create([
            'sale_id' => $sale->id,
            'user_id' => $affiliate->id,
            'coupon_code_id' => $affiliate->couponCodes->first()->id,
        ]);

        // No settlement row exists while a sale is live, so there is nothing
        // to attach an invoice to.
        $this->actingAs($affiliate)
            ->post(route('sales.invoice.store', $sale), [
                'invoice' => UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf'),
            ])
            ->assertNotFound();

        Storage::disk('invoices')->assertDirectoryEmpty('/');
    }

    public function test_an_invoice_can_be_uploaded_once_the_sale_has_ended(): void
    {
        Storage::fake('invoices');
        Mail::fake();

        [$affiliate, $sale] = $this->endedSaleWithOrders();

        app(SettlementService::class)->closeSale($sale);

        $this->actingAs($affiliate)
            ->post(route('sales.invoice.store', $sale), [
                'invoice' => UploadedFile::fake()->create('my-invoice.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect();

        $settlement = Settlement::where('user_id', $affiliate->id)->firstOrFail();

        $this->assertSame(Settlement::STATUS_INVOICE_UPLOADED, $settlement->status);
        $this->assertSame('my-invoice.pdf', $settlement->invoice_original_name);
        Storage::disk('invoices')->assertExists($settlement->invoice_path);
    }

    public function test_the_report_is_frozen_when_the_sale_closes(): void
    {
        Mail::fake();

        [$affiliate, $sale] = $this->endedSaleWithOrders(orders: 4, amount: 1000);

        app(SettlementService::class)->closeSale($sale);

        $settlement = Settlement::where('user_id', $affiliate->id)->firstOrFail();
        $this->assertSame(4, $settlement->units_sold);
        $this->assertSame('4000.00', $settlement->gross_earnings);

        // A late order arriving after close must not move a locked report.
        Order::factory()->create([
            'sale_id' => $sale->id,
            'user_id' => $affiliate->id,
            'coupon_code_id' => $affiliate->couponCodes->first()->id,
            'converted_amount' => 9999,
        ]);

        $summary = app(\App\Services\SaleSummaryService::class)->for($affiliate, $sale->fresh());

        $this->assertSame(4, $summary->unitsSold);
        $this->assertSame(4000.00, $summary->grossEarnings);
    }

    /**
     * Spec section 6.2: settlement emails are always sent, whatever the
     * creator's activity switches say.
     */
    public function test_the_sale_ended_email_is_sent_even_with_all_activity_emails_switched_off(): void
    {
        Mail::fake();

        [$affiliate, $sale] = $this->endedSaleWithOrders();

        $affiliate->profile->update([
            'notify_master' => false,
            'notify_on_sale' => false,
            'notify_weekly_summary' => false,
        ]);

        app(SettlementService::class)->closeSale($sale);

        Mail::assertSent(SaleEndedMail::class, 1);
    }

    public function test_closing_a_sale_twice_does_not_email_the_creator_twice(): void
    {
        Mail::fake();

        [$affiliate, $sale] = $this->endedSaleWithOrders();

        $service = app(SettlementService::class);
        $service->closeSale($sale);
        $service->closeSale($sale->fresh());

        Mail::assertSent(SaleEndedMail::class, 1);
    }

    /**
     * Spec section 5.7: "Recording that payment (amount and date) is what
     * triggers the creator's payment-confirmation email and flips the status
     * to Paid."
     */
    public function test_recording_a_payment_emails_the_creator_and_flips_the_status(): void
    {
        Mail::fake();

        [$affiliate, $sale] = $this->endedSaleWithOrders();
        app(SettlementService::class)->closeSale($sale);

        $admin = User::factory()->admin()->create();
        $settlement = Settlement::where('user_id', $affiliate->id)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.settlements.pay', $settlement), [
                'paid_amount' => 1234.56,
                'paid_on' => now()->toDateString(),
                'payment_reference' => 'NEFT/000123',
            ])
            ->assertRedirect();

        $settlement->refresh();

        $this->assertSame(Settlement::STATUS_PAID, $settlement->status);
        $this->assertSame('1234.56', $settlement->paid_amount);
        $this->assertSame($admin->id, $settlement->paid_by_user_id);

        Mail::assertSent(PaymentConfirmationMail::class, 1);
    }

    public function test_a_creator_whose_orders_were_all_refunded_gets_no_settlement(): void
    {
        Mail::fake();

        $affiliate = $this->makeAffiliate();
        $sale = Sale::factory()->ended()->create();

        Order::factory()->refunded()->count(2)->create([
            'sale_id' => $sale->id,
            'user_id' => $affiliate->id,
            'coupon_code_id' => $affiliate->couponCodes->first()->id,
        ]);

        app(SettlementService::class)->closeSale($sale);

        $this->assertDatabaseCount('settlements', 0);
        Mail::assertNotSent(SaleEndedMail::class);
    }

    public function test_the_report_download_is_refused_while_a_sale_is_live(): void
    {
        $affiliate = $this->makeAffiliate();
        $sale = Sale::factory()->live()->create();

        Order::factory()->create([
            'sale_id' => $sale->id,
            'user_id' => $affiliate->id,
            'coupon_code_id' => $affiliate->couponCodes->first()->id,
        ]);

        $this->actingAs($affiliate)
            ->get(route('sales.report', $sale))
            ->assertForbidden();
    }

    /**
     * @return array{0: \App\Models\User, 1: Sale}
     */
    private function endedSaleWithOrders(int $orders = 2, float $amount = 1000): array
    {
        $affiliate = $this->makeAffiliate(commissionRate: 0.15);
        $sale = Sale::factory()->ended()->create();

        Order::factory()->count($orders)->create([
            'sale_id' => $sale->id,
            'user_id' => $affiliate->id,
            'coupon_code_id' => $affiliate->couponCodes->first()->id,
            'amount' => $amount,
            'converted_amount' => $amount,
        ]);

        return [$affiliate, $sale];
    }
}
