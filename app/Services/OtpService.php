<?php

namespace App\Services;

use App\Mail\LoginCodeMail;
use App\Models\LoginCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The emailed sign-in code (spec section 3, step 4).
 *
 * The spec is direct about this being the step that actually secures the
 * account -- date of birth "can be looked up or guessed" and "must not be the
 * only thing protecting payout information" -- so the code is:
 *
 *  - random, from a cryptographically secure source
 *  - stored hashed, never in the clear
 *  - single use, and expired after a few minutes
 *  - burned after a handful of wrong guesses, so it cannot be brute forced
 */
final class OtpService
{
    /**
     * Issue a fresh code and email it. Any earlier unconsumed codes for this
     * user are invalidated, so only the most recent email will work.
     */
    public function issue(User $user, Request $request): LoginCode
    {
        $this->invalidateOutstanding($user);

        $code = $this->generateCode();

        $record = LoginCode::create([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'expires_at' => Carbon::now()->addMinutes((int) config('affiliate.otp.ttl_minutes', 10)),
            'attempts' => 0,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);

        Mail::to($user->email)->send(new LoginCodeMail($user, $code));

        Log::channel('audit')->info('Sign-in code issued', [
            'user_id' => $user->id,
            'ip' => $request->ip(),
        ]);

        return $record;
    }

    /**
     * Check a submitted code. Consumes it on success.
     */
    public function verify(User $user, string $submitted): bool
    {
        $record = $this->currentFor($user);

        if (! $record || ! $record->isUsable()) {
            return false;
        }

        // Counted before the comparison, so an attacker cannot avoid the
        // attempt limit by abandoning the request mid-check.
        $record->increment('attempts');

        if (! Hash::check($submitted, $record->code_hash)) {
            Log::channel('audit')->info('Sign-in code rejected', [
                'user_id' => $user->id,
                'attempts' => $record->attempts,
            ]);

            return false;
        }

        $record->forceFill(['consumed_at' => Carbon::now()])->save();

        return true;
    }

    /**
     * Whether a new code can be sent yet, to stop the resend button being used
     * as a way to spam a creator's inbox.
     */
    public function canResend(User $user): bool
    {
        $latest = $this->currentFor($user);

        if (! $latest) {
            return true;
        }

        $cooldown = (int) config('affiliate.otp.resend_cooldown_seconds', 60);

        return $latest->created_at->addSeconds($cooldown)->isPast();
    }

    public function secondsUntilResend(User $user): int
    {
        $latest = $this->currentFor($user);

        if (! $latest) {
            return 0;
        }

        $cooldown = (int) config('affiliate.otp.resend_cooldown_seconds', 60);
        $readyAt = $latest->created_at->addSeconds($cooldown);

        return max(0, (int) Carbon::now()->diffInSeconds($readyAt, false));
    }

    private function currentFor(User $user): ?LoginCode
    {
        return LoginCode::where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();
    }

    private function invalidateOutstanding(User $user): void
    {
        LoginCode::where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => Carbon::now()]);
    }

    /**
     * A zero-padded code of the configured length, from random_int rather than
     * rand() so it is not predictable from a previous code.
     */
    private function generateCode(): string
    {
        $length = (int) config('affiliate.otp.length', 6);
        $max = (10 ** $length) - 1;

        return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }
}
