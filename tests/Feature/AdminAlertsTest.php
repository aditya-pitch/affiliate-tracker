<?php

namespace Tests\Feature;

use App\Mail\AdminAlertMail;
use App\Models\Order;
use App\Models\Sale;
use App\Models\Settlement;
use App\Models\User;
use App\Services\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Operational updates to our own team's address.
 */
class AdminAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['affiliate.admin.email' => 'adityapahuja@pitchinnovations.com']);
    }

    public function test_closing_a_sale_tells_our_team(): void
    {
        $creator = $this->makeAffiliate();
        $sale = Sale::factory()->ended()->create();

        Order::factory()->create([
            'sale_id' => $sale->id,
            'user_id' => $creator->id,
            'coupon_code_id' => $creator->couponCodes->first()->id,
            'converted_amount' => 10000,
        ]);

        app(SettlementService::class)->closeSale($sale);

        Mail::assertSent(AdminAlertMail::class, function (AdminAlertMail $mail) {
            return $mail->hasTo('adityapahuja@pitchinnovations.com')
                && str_contains($mail->headline, 'closed out');
        });
    }

    public function test_an_uploaded_invoice_tells_our_team(): void
    {
        Storage::fake('invoices');

        $creator = $this->makeAffiliate();
        $sale = Sale::factory()->ended()->create();

        Order::factory()->create([
            'sale_id' => $sale->id,
            'user_id' => $creator->id,
            'coupon_code_id' => $creator->couponCodes->first()->id,
            'converted_amount' => 5000,
        ]);

        app(SettlementService::class)->closeSale($sale, notify: false);

        $this->actingAs($creator)->post(route('sales.invoice.store', $sale), [
            'invoice' => UploadedFile::fake()->create('invoice.pdf', 50, 'application/pdf'),
        ]);

        Mail::assertSent(AdminAlertMail::class, function (AdminAlertMail $mail) {
            return $mail->hasTo('adityapahuja@pitchinnovations.com')
                && str_contains($mail->headline, 'invoice');
        });
    }

    public function test_recording_a_payment_tells_our_team(): void
    {
        $creator = $this->makeAffiliate();
        $sale = Sale::factory()->ended()->create();

        Order::factory()->create([
            'sale_id' => $sale->id,
            'user_id' => $creator->id,
            'coupon_code_id' => $creator->couponCodes->first()->id,
            'converted_amount' => 10000,
        ]);

        app(SettlementService::class)->closeSale($sale, notify: false);

        $settlement = Settlement::where('user_id', $creator->id)->firstOrFail();

        app(SettlementService::class)->markPaid(
            settlement: $settlement,
            amount: 847.46,
            paidOn: Carbon::now(),
            reference: 'NEFT/1',
            admin: User::factory()->admin()->create(),
        );

        Mail::assertSent(AdminAlertMail::class, function (AdminAlertMail $mail) {
            return str_contains($mail->headline, 'payment was recorded');
        });
    }

    public function test_alerts_can_be_switched_off_entirely(): void
    {
        config(['affiliate.admin_alerts.enabled' => false]);

        $creator = $this->makeAffiliate();
        $sale = Sale::factory()->ended()->create();

        Order::factory()->create([
            'sale_id' => $sale->id,
            'user_id' => $creator->id,
            'coupon_code_id' => $creator->couponCodes->first()->id,
            'converted_amount' => 10000,
        ]);

        app(SettlementService::class)->closeSale($sale);

        Mail::assertNotSent(AdminAlertMail::class);
    }

    /**
     * A typo'd ADMIN_EMAIL should be logged and skipped, not blow up whatever
     * was happening at the time.
     */
    public function test_an_invalid_admin_address_is_skipped_rather_than_thrown(): void
    {
        config(['affiliate.admin.email' => 'not-an-email']);

        $creator = $this->makeAffiliate();
        $sale = Sale::factory()->ended()->create();

        Order::factory()->create([
            'sale_id' => $sale->id,
            'user_id' => $creator->id,
            'coupon_code_id' => $creator->couponCodes->first()->id,
            'converted_amount' => 10000,
        ]);

        app(SettlementService::class)->closeSale($sale);

        // The close-out still completed.
        $this->assertDatabaseCount('settlements', 1);
        Mail::assertNotSent(AdminAlertMail::class);
    }
}
