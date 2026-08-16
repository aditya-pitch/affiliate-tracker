<?php

namespace Tests\Feature;

use App\Mail\LoginCodeMail;
use App\Models\LoginCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The four-step sign-in from spec section 3.
 *
 * The spec is explicit that date of birth is weak on its own and "must not be
 * the only thing protecting payout information" — the emailed code is the real
 * control. So the tests that matter here are the ones proving a step cannot be
 * skipped and the code cannot be brute-forced.
 */
class LoginFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_creator_can_sign_in_by_completing_all_four_steps(): void
    {
        $affiliate = $this->makeAffiliate();
        $affiliate->update(['date_of_birth' => '1994-03-12']);

        // 1. Email
        $this->post(route('login.email'), ['email' => $affiliate->email])
            ->assertRedirect(route('login.password'));

        // 2. Password
        $this->post(route('login.password'), ['password' => 'password'])
            ->assertRedirect(route('login.dob'));

        $this->assertGuest();

        // 3. Date of birth — this is what triggers the emailed code
        $this->post(route('login.dob'), ['date_of_birth' => '1994-03-12'])
            ->assertRedirect(route('login.code'));

        $this->assertGuest();

        $code = $this->capturedCode();
        $this->assertNotNull($code);

        // 4. The emailed code
        $this->post(route('login.code'), ['code' => $code])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($affiliate);
    }

    public function test_the_steps_cannot_be_skipped(): void
    {
        $affiliate = $this->makeAffiliate();

        // Straight to the final step with no flow at all.
        $this->post(route('login.code'), ['code' => '123456'])
            ->assertRedirect(route('login'));

        $this->assertGuest();

        // Straight to the date of birth step having never given a password.
        $this->post(route('login.email'), ['email' => $affiliate->email]);
        $this->post(route('login.dob'), ['date_of_birth' => '1994-03-12'])
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_a_wrong_password_does_not_send_a_code(): void
    {
        $affiliate = $this->makeAffiliate();

        $this->post(route('login.email'), ['email' => $affiliate->email]);
        $this->post(route('login.password'), ['password' => 'not-the-password'])
            ->assertSessionHasErrors('login');

        Mail::assertNothingSent();
        $this->assertGuest();
    }

    public function test_the_wrong_date_of_birth_does_not_sign_anyone_in(): void
    {
        $affiliate = $this->makeAffiliate();
        $affiliate->update(['date_of_birth' => '1994-03-12']);

        $this->post(route('login.email'), ['email' => $affiliate->email]);
        $this->post(route('login.password'), ['password' => 'password']);
        $this->post(route('login.dob'), ['date_of_birth' => '1990-01-01'])
            ->assertSessionHasErrors('login');

        Mail::assertNothingSent();
        $this->assertGuest();
    }

    /**
     * The one that stops the emailed code being guessed: it burns out after a
     * handful of wrong attempts, so an attacker cannot walk 000000–999999.
     */
    public function test_the_emailed_code_is_burned_after_too_many_wrong_guesses(): void
    {
        $affiliate = $this->makeAffiliate();
        $affiliate->update(['date_of_birth' => '1994-03-12']);

        $this->post(route('login.email'), ['email' => $affiliate->email]);
        $this->post(route('login.password'), ['password' => 'password']);
        $this->post(route('login.dob'), ['date_of_birth' => '1994-03-12']);

        $realCode = $this->capturedCode();

        $maxAttempts = (int) config('affiliate.otp.max_attempts', 5);

        for ($i = 0; $i < $maxAttempts; $i++) {
            $this->post(route('login.code'), ['code' => '000000']);
        }

        // Even the correct code is no good now.
        $this->post(route('login.code'), ['code' => $realCode]);

        $this->assertGuest();
        $this->assertTrue(LoginCode::where('user_id', $affiliate->id)->first()->isExhausted());
    }

    public function test_a_code_cannot_be_used_twice(): void
    {
        $affiliate = $this->makeAffiliate();
        $affiliate->update(['date_of_birth' => '1994-03-12']);

        $this->post(route('login.email'), ['email' => $affiliate->email]);
        $this->post(route('login.password'), ['password' => 'password']);
        $this->post(route('login.dob'), ['date_of_birth' => '1994-03-12']);

        $code = $this->capturedCode();

        $this->post(route('login.code'), ['code' => $code]);
        $this->assertAuthenticatedAs($affiliate);

        $this->post(route('logout'));
        $this->assertGuest();

        // Same code, second time round.
        $this->post(route('login.email'), ['email' => $affiliate->email]);
        $this->post(route('login.password'), ['password' => 'password']);
        $this->post(route('login.dob'), ['date_of_birth' => '1994-03-12']);
        $this->post(route('login.code'), ['code' => $code]);

        // A fresh code was issued at the date-of-birth step, so the old one is
        // no longer the current code and cannot sign anyone in.
        $this->assertGuest();
    }

    /**
     * The sign-in form must not be usable to work out which of our creators
     * has an account — it advances identically either way.
     */
    public function test_an_unknown_email_behaves_exactly_like_a_known_one(): void
    {
        $this->post(route('login.email'), ['email' => 'nobody@example.com'])
            ->assertRedirect(route('login.password'));

        $this->post(route('login.password'), ['password' => 'whatever'])
            ->assertSessionHasErrors('login');

        Mail::assertNothingSent();
    }

    public function test_an_inactive_account_cannot_sign_in(): void
    {
        $affiliate = $this->makeAffiliate();
        $affiliate->update(['is_active' => false, 'date_of_birth' => '1994-03-12']);

        $this->post(route('login.email'), ['email' => $affiliate->email]);
        $this->post(route('login.password'), ['password' => 'password'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    /**
     * Pull the code out of the faked email, the way a creator would read it
     * out of their inbox.
     */
    private function capturedCode(): ?string
    {
        $code = null;

        Mail::assertSent(LoginCodeMail::class, function (LoginCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return $code;
    }
}
