<?php

namespace Tests\Feature;

use App\Mail\CreatorWelcomeMail;
use App\Models\CouponCode;
use App\Models\Order;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The internal screens for setting creators up and looking after their
 * dashboards.
 */
class AdminCreatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_set_a_creator_up_from_scratch(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.creators.store'), [
            'name' => 'Nadia Fernandes',
            'email' => 'nadia@example.com',
            'date_of_birth' => '1996-02-20',
            'commission_rate' => '18.5',
            'payout_currency' => 'USD',
            'country_code' => 'PT',
            'codes' => 'NADIA20, NADIASUMMER',
            'sale_notification_frequency' => 'hourly',
        ]);

        $creator = User::where('email', 'nadia@example.com')->firstOrFail();

        $response->assertRedirect(route('admin.creators.show', $creator));

        $this->assertTrue($creator->isAffiliate());
        $this->assertTrue($creator->is_active);

        // Entered as a percentage, stored as a fraction.
        $this->assertSame('0.1850', $creator->profile->commission_rate);
        $this->assertSame('USD', $creator->profile->payout_currency);
        $this->assertSame('hourly', $creator->profile->sale_notification_frequency);

        // Codes are normalised and both attached.
        $this->assertEqualsCanonicalizing(
            ['NADIA20', 'NADIASUMMER'],
            $creator->couponCodes->pluck('code')->all()
        );
    }

    /**
     * The password is shown once and never stored in a readable form. This is
     * the test that stops someone "helpfully" adding a plaintext column later.
     */
    public function test_the_issued_password_is_shown_once_and_stored_only_as_a_hash(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.creators.store'), [
            'name' => 'Nadia Fernandes',
            'email' => 'nadia@example.com',
            'date_of_birth' => '1996-02-20',
            'commission_rate' => '15',
            'payout_currency' => 'INR',
            'codes' => 'NADIA15',
            'sale_notification_frequency' => 'immediate',
        ]);

        $password = session('issued_password');

        $this->assertNotEmpty($password);

        $creator = User::where('email', 'nadia@example.com')->firstOrFail();

        // What is stored is a hash, not the password.
        $this->assertNotSame($password, $creator->password);
        $this->assertTrue(Hash::check($password, $creator->password));

        // And the password really does work at the password step. Signing the
        // admin out first, otherwise the guest middleware bounces us straight
        // back to their own landing page.
        $this->post(route('logout'));

        $this->post(route('login.email'), ['email' => 'nadia@example.com']);
        $this->post(route('login.password'), ['password' => $password])
            ->assertRedirect(route('login.dob'));
    }

    public function test_creating_a_creator_can_email_them_their_login_details(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.creators.store'), [
            'name' => 'Nadia Fernandes',
            'email' => 'nadia@example.com',
            'date_of_birth' => '1996-02-20',
            'commission_rate' => '15',
            'payout_currency' => 'INR',
            'codes' => 'NADIA15',
            'sale_notification_frequency' => 'immediate',
            'send_welcome' => '1',
        ]);

        Mail::assertSent(CreatorWelcomeMail::class, function (CreatorWelcomeMail $mail) {
            // The password is only ever in the email when it was just generated.
            return $mail->user->email === 'nadia@example.com' && $mail->password !== null;
        });

        $this->assertNotNull(User::where('email', 'nadia@example.com')->first()->welcome_sent_at);
    }

    public function test_resending_instructions_later_cannot_include_a_password(): void
    {
        $admin = User::factory()->admin()->create();
        $creator = $this->makeAffiliate();

        $this->actingAs($admin)
            ->post(route('admin.creators.welcome', $creator))
            ->assertRedirect();

        Mail::assertSent(CreatorWelcomeMail::class, function (CreatorWelcomeMail $mail) {
            // By this point the password is only a hash, so there is nothing
            // to send -- the email tells them to ask for a new one instead.
            return $mail->password === null;
        });
    }

    public function test_issuing_a_new_password_replaces_the_old_one(): void
    {
        $admin = User::factory()->admin()->create();
        $creator = $this->makeAffiliate();

        $originalHash = $creator->password;

        $this->actingAs($admin)
            ->post(route('admin.creators.password', $creator), ['send_email' => '1'])
            ->assertRedirect();

        $issued = session('issued_password');
        $creator->refresh();

        $this->assertNotEmpty($issued);
        $this->assertNotSame($originalHash, $creator->password);
        $this->assertTrue(Hash::check($issued, $creator->password));
        $this->assertFalse(Hash::check('password', $creator->password));

        Mail::assertSent(CreatorWelcomeMail::class);
    }

    public function test_a_duplicate_email_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $existing = $this->makeAffiliate();

        $this->actingAs($admin)
            ->post(route('admin.creators.store'), [
                'name' => 'Someone Else',
                'email' => $existing->email,
                'date_of_birth' => '1996-02-20',
                'commission_rate' => '15',
                'payout_currency' => 'INR',
                'codes' => 'ANOTHER',
                'sale_notification_frequency' => 'immediate',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_a_coupon_code_cannot_be_given_to_two_creators(): void
    {
        $admin = User::factory()->admin()->create();
        $first = $this->makeAffiliate(code: 'SHARED');
        $second = $this->makeAffiliate(code: 'OTHER');

        $this->actingAs($admin)
            ->post(route('admin.creators.codes.add', $second), ['code' => 'SHARED'])
            ->assertSessionHasErrors('code');

        $this->assertSame(1, CouponCode::where('code', 'SHARED')->count());
    }

    public function test_an_admin_can_view_a_creators_dashboard_read_only(): void
    {
        $admin = User::factory()->admin()->create();
        $creator = $this->makeAffiliate(code: 'THEIRS');
        $sale = Sale::factory()->closed()->create();

        Order::factory()->create([
            'sale_id' => $sale->id,
            'user_id' => $creator->id,
            'coupon_code_id' => $creator->couponCodes->first()->id,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.creators.dashboard', [$creator, $sale]));

        $response->assertOk();
        $response->assertSee('Internal view');
        $response->assertSee($creator->name);

        // The settlement actions belong to the creator, not to us.
        $response->assertDontSee('Upload invoice');
        $response->assertDontSee('Download .xlsx');
    }

    /**
     * Spec section 9 again, from the other direction: none of this is reachable
     * by a creator.
     */
    public function test_a_creator_cannot_reach_any_of_the_creator_management_screens(): void
    {
        $creator = $this->makeAffiliate();
        $other = $this->makeAffiliate(code: 'OTHER');

        $this->actingAs($creator)->get(route('admin.creators.index'))->assertForbidden();
        $this->actingAs($creator)->get(route('admin.creators.create'))->assertForbidden();
        $this->actingAs($creator)->get(route('admin.creators.show', $other))->assertForbidden();
        $this->actingAs($creator)->post(route('admin.creators.password', $other))->assertForbidden();
        $this->actingAs($creator)->post(route('admin.creators.welcome', $other))->assertForbidden();
        $this->actingAs($creator)->post(route('admin.creators.store'), [])->assertForbidden();
    }

    public function test_an_admin_signing_in_lands_on_the_overview_not_a_creator_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertRedirect(route('admin.overview.index'));
    }
}
