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
use RuntimeException;
use Tests\TestCase;

/**
 * What happens when email does not work.
 *
 * Sign-in depends on an emailed code, so mail is not a nice-to-have here — a
 * broken transport can lock everyone out of their own earnings. These tests
 * cover the two things that matter: nothing crashes, and nothing that already
 * happened gets undone because a courtesy email failed.
 */
class MailResilienceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Make every send throw, the way a misconfigured SMTP host would.
     */
    private function breakMail(): void
    {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('Connection could not be established'));
    }

    public function test_a_failed_sign_in_code_does_not_crash_the_login(): void
    {
        $creator = $this->makeAffiliate();
        $creator->update(['date_of_birth' => '1994-03-12']);

        $this->post(route('login.email'), ['email' => $creator->email]);
        $this->post(route('login.password'), ['password' => 'password'])
            ->assertRedirect(route('login.dob'));

        $this->breakMail();

        // The date-of-birth step is what sends the code.
        $response = $this->post(route('login.dob'), ['date_of_birth' => '1994-03-12']);

        $response->assertSessionHasErrors('login');
        $this->assertGuest();

        // Still at the date-of-birth step, so they can simply try again once
        // mail is fixed -- rather than being thrown back to the start.
        $this->get(route('login.dob'))->assertOk();
    }

    public function test_a_failed_order_email_does_not_prevent_the_order_being_recorded(): void
    {
        $creator = $this->makeAffiliate();
        $sale = Sale::factory()->live()->create();

        $this->breakMail();

        $order = Order::factory()->create([
            'sale_id' => $sale->id,
            'user_id' => $creator->id,
            'coupon_code_id' => $creator->couponCodes->first()->id,
            'converted_amount' => 5000,
        ]);

        // The sale happened and the commission is owed regardless.
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    public function test_a_failed_confirmation_email_does_not_undo_a_recorded_payment(): void
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
        $admin = User::factory()->admin()->create();

        $this->breakMail();

        app(SettlementService::class)->markPaid(
            settlement: $settlement,
            amount: 847.46,
            paidOn: Carbon::now(),
            reference: 'NEFT/1',
            admin: $admin,
        );

        $settlement->refresh();

        // The money went out in the real world; the record must reflect that.
        $this->assertSame(Settlement::STATUS_PAID, $settlement->status);
        $this->assertSame('847.46', $settlement->paid_amount);
    }

    public function test_one_creators_bouncing_address_does_not_stop_the_others_being_told(): void
    {
        $sale = Sale::factory()->ended()->create();

        foreach (range(1, 3) as $i) {
            $creator = $this->makeAffiliate(code: "CODE{$i}");
            Order::factory()->create([
                'sale_id' => $sale->id,
                'user_id' => $creator->id,
                'coupon_code_id' => $creator->couponCodes->first()->id,
                'converted_amount' => 1000,
            ]);
        }

        app(SettlementService::class)->closeSale($sale);

        // All three got settlements even though the mail layer is faked.
        $this->assertDatabaseCount('settlements', 3);
    }

    public function test_a_failed_invoice_alert_does_not_lose_the_invoice(): void
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

        $this->breakMail();

        $this->actingAs($creator)->post(route('sales.invoice.store', $sale), [
            'invoice' => UploadedFile::fake()->create('invoice.pdf', 50, 'application/pdf'),
        ]);

        $settlement = Settlement::where('user_id', $creator->id)->firstOrFail();

        $this->assertSame(Settlement::STATUS_INVOICE_UPLOADED, $settlement->status);
        Storage::disk('invoices')->assertExists($settlement->invoice_path);
    }
}
