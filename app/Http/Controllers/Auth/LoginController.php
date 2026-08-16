<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LoginFlow;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The four-step sign-in from spec section 3.
 *
 * A note on the deliberate awkwardness in here: the email step advances even
 * when the address has no account, and the password and date-of-birth steps
 * both fail with the same wording. That is on purpose. Sign-in is the one
 * public surface on a dashboard holding payout information, and a form that
 * says "no account with that email" is a free tool for working out which of our
 * creators to go after.
 */
class LoginController extends Controller
{
    /** Wrong answers at one step before the whole flow is thrown away. */
    private const MAX_STEP_FAILURES = 5;

    public function __construct(
        private readonly LoginFlow $flow,
        private readonly OtpService $otp,
    ) {}

    // --- Step 1: email ---------------------------------------------------

    public function showEmail(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.email');
    }

    public function submitEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $this->assertNotRateLimited($request, 'email');

        $this->flow->start(strtolower(trim($validated['email'])));

        return redirect()->route('login.password');
    }

    // --- Step 2: password ------------------------------------------------

    public function showPassword(): View|RedirectResponse
    {
        if (! $this->flow->isAt(LoginFlow::STAGE_PASSWORD)) {
            return $this->restart();
        }

        return view('auth.password', ['email' => $this->flow->email()]);
    }

    public function submitPassword(Request $request): RedirectResponse
    {
        if (! $this->flow->isAt(LoginFlow::STAGE_PASSWORD)) {
            return $this->restart();
        }

        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $this->assertNotRateLimited($request, 'password');

        $user = User::with('profile')->where('email', $this->flow->email())->first();

        /*
         | Hash::check is run even when there is no such user, against a dummy
         | hash, so a missing account and a wrong password take the same amount
         | of time. Otherwise the response time alone tells an attacker which
         | emails are real.
         */
        $passwordMatches = $user
            ? Hash::check($validated['password'], $user->password)
            : Hash::check($validated['password'], $this->dummyHash());

        if (! $passwordMatches || ! $user || ! $user->is_active) {
            RateLimiter::hit($this->throttleKey($request, 'password'));

            return $this->failStep('These details do not match our records.');
        }

        RateLimiter::clear($this->throttleKey($request, 'password'));

        $this->flow->advanceToDob($user);

        return redirect()->route('login.dob');
    }

    // --- Step 3: date of birth -------------------------------------------

    public function showDob(): View|RedirectResponse
    {
        if (! $this->flow->isAt(LoginFlow::STAGE_DOB)) {
            return $this->restart();
        }

        return view('auth.dob');
    }

    public function submitDob(Request $request): RedirectResponse
    {
        if (! $this->flow->isAt(LoginFlow::STAGE_DOB)) {
            return $this->restart();
        }

        $validated = $request->validate([
            'date_of_birth' => ['required', 'date', 'before:today'],
        ]);

        $this->assertNotRateLimited($request, 'dob');

        $user = $this->flow->user();

        if (! $user) {
            return $this->restart();
        }

        $submitted = Carbon::parse($validated['date_of_birth'])->toDateString();
        $onRecord = $user->date_of_birth?->toDateString();

        if ($onRecord === null || ! hash_equals($onRecord, $submitted)) {
            RateLimiter::hit($this->throttleKey($request, 'dob'));

            return $this->failStep('These details do not match our records.');
        }

        RateLimiter::clear($this->throttleKey($request, 'dob'));

        // Only now do we send anything to the creator's inbox -- an attacker
        // who does not have the password cannot use this to spam them.
        $this->otp->issue($user, $request);
        $this->flow->advanceToCode();

        return redirect()->route('login.code');
    }

    // --- Step 4: emailed one-time code -----------------------------------

    public function showCode(): View|RedirectResponse
    {
        if (! $this->flow->isAt(LoginFlow::STAGE_CODE)) {
            return $this->restart();
        }

        $user = $this->flow->user();

        return view('auth.code', [
            'maskedEmail' => $user ? $this->maskEmail($user->email) : null,
            'secondsUntilResend' => $user ? $this->otp->secondsUntilResend($user) : 0,
        ]);
    }

    public function submitCode(Request $request): RedirectResponse
    {
        if (! $this->flow->isAt(LoginFlow::STAGE_CODE)) {
            return $this->restart();
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'digits:'.config('affiliate.otp.length', 6)],
        ]);

        $this->assertNotRateLimited($request, 'code');

        $user = $this->flow->user();

        if (! $user) {
            return $this->restart();
        }

        if (! $this->otp->verify($user, $validated['code'])) {
            RateLimiter::hit($this->throttleKey($request, 'code'));

            return $this->failStep('That code was not correct, or it has expired. Check the latest email, or send yourself a new code.');
        }

        RateLimiter::clear($this->throttleKey($request, 'code'));

        return $this->completeSignIn($request, $user);
    }

    public function resendCode(Request $request): RedirectResponse
    {
        if (! $this->flow->isAt(LoginFlow::STAGE_CODE)) {
            return $this->restart();
        }

        $user = $this->flow->user();

        if (! $user) {
            return $this->restart();
        }

        if (! $this->otp->canResend($user)) {
            return back()->with('status', 'Hold on a moment before asking for another code.');
        }

        $this->otp->issue($user, $request);

        return back()->with('status', 'A new code is on its way to your inbox.');
    }

    // --- Sign out --------------------------------------------------------

    public function logout(Request $request): RedirectResponse
    {
        $userId = Auth::id();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Log::channel('audit')->info('Signed out', ['user_id' => $userId]);

        return redirect()->route('login')->with('status', 'You have been signed out.');
    }

    // --- Internals -------------------------------------------------------

    private function completeSignIn(Request $request, User $user): RedirectResponse
    {
        Auth::login($user);

        // New session id on privilege change, so a session token captured
        // before sign-in cannot be reused afterwards.
        $request->session()->regenerate();

        $this->flow->reset();

        $user->forceFill(['last_login_at' => Carbon::now()])->save();

        Log::channel('audit')->info('Signed in', [
            'user_id' => $user->id,
            'role' => $user->role,
            'ip' => $request->ip(),
        ]);

        return redirect()->intended(
            $user->isAdmin() ? route('admin.settlements.index') : route('dashboard')
        );
    }

    /**
     * Record a wrong answer, and throw the flow away once there have been too
     * many. Sending them back to the start means an attacker who has guessed a
     * password still has to re-supply it after five wrong dates of birth.
     */
    private function failStep(string $message): RedirectResponse
    {
        if ($this->flow->recordFailure() >= self::MAX_STEP_FAILURES) {
            $this->flow->reset();

            return redirect()->route('login')
                ->withErrors(['email' => 'Too many incorrect attempts. Please start again.']);
        }

        return back()->withErrors(['login' => $message]);
    }

    private function restart(): RedirectResponse
    {
        $this->flow->reset();

        return redirect()->route('login')
            ->withErrors(['email' => 'Your sign-in timed out. Please start again.']);
    }

    /**
     * A throwaway hash to compare against when the email has no account.
     *
     * Generated through the configured hasher rather than hard-coded, so it
     * always costs exactly what a real password check costs -- a literal
     * pinned at some other cost factor would leave the timing difference this
     * is here to remove. Computed at most once per process.
     */
    private function dummyHash(): string
    {
        static $hash = null;

        return $hash ??= Hash::make('not-a-real-password-'.Str::random(16));
    }

    private function assertNotRateLimited(Request $request, string $step): void
    {
        $key = $this->throttleKey($request, $step);

        if (! RateLimiter::tooManyAttempts($key, maxAttempts: 10)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'login' => "Too many attempts. Please try again in {$seconds} seconds.",
        ]);
    }

    private function throttleKey(Request $request, string $step): string
    {
        return 'login:'.$step.'|'.($this->flow->email() ?? 'anon').'|'.$request->ip();
    }

    /**
     * aarav@example.com -> a•••v@example.com
     *
     * Enough for a creator to recognise their own inbox, not enough to be
     * useful to someone who has got this far without it.
     */
    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        $masked = mb_strlen($local) <= 2
            ? str_repeat('•', mb_strlen($local))
            : mb_substr($local, 0, 1).str_repeat('•', max(1, mb_strlen($local) - 2)).mb_substr($local, -1);

        return $masked.'@'.$domain;
    }
}
