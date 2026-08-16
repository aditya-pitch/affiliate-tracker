<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Session;

/**
 * The four-step sign-in from spec section 3, held in the session.
 *
 * Email -> password -> date of birth -> emailed one-time code, in that order.
 *
 * Two rules this class exists to enforce:
 *
 *  - A step cannot be skipped. Each handler asks whether the flow is actually
 *    at that stage; posting straight to the code endpoint gets you sent back
 *    to the beginning.
 *  - Nothing is authenticated until the final step. The user id is only put in
 *    the session once the password has been verified, and even then it only
 *    identifies who the flow is *about* -- the user is not logged in until the
 *    emailed code has been checked.
 */
final class LoginFlow
{
    private const KEY = 'login_flow';

    /** How long a half-finished sign-in stays valid. */
    private const TTL_MINUTES = 15;

    public const STAGE_EMAIL = 'email';

    public const STAGE_PASSWORD = 'password';

    public const STAGE_DOB = 'dob';

    public const STAGE_CODE = 'code';

    /**
     * Begin the flow. The email is recorded but never confirmed as existing --
     * the flow advances identically whether or not the account is real, so the
     * form cannot be used to discover which emails have accounts.
     */
    public function start(string $email): void
    {
        Session::put(self::KEY, [
            'stage' => self::STAGE_PASSWORD,
            'email' => $email,
            'user_id' => null,
            'failures' => 0,
            'started_at' => Carbon::now()->timestamp,
        ]);
    }

    public function advanceToDob(User $user): void
    {
        $this->update([
            'stage' => self::STAGE_DOB,
            'user_id' => $user->id,
            'failures' => 0,
        ]);
    }

    public function advanceToCode(): void
    {
        $this->update([
            'stage' => self::STAGE_CODE,
            'failures' => 0,
        ]);
    }

    public function stage(): string
    {
        if ($this->hasExpired()) {
            $this->reset();

            return self::STAGE_EMAIL;
        }

        return $this->state()['stage'] ?? self::STAGE_EMAIL;
    }

    public function isAt(string $stage): bool
    {
        return $this->stage() === $stage;
    }

    public function email(): ?string
    {
        return $this->state()['email'] ?? null;
    }

    /**
     * The user this flow is about, once the password step has passed.
     * Null before that, by design.
     */
    public function user(): ?User
    {
        $id = $this->state()['user_id'] ?? null;

        return $id ? User::with('profile')->find($id) : null;
    }

    /**
     * Count a wrong answer at the current step. Returns the running total so
     * the caller can decide when to throw the whole flow away.
     */
    public function recordFailure(): int
    {
        $failures = (int) ($this->state()['failures'] ?? 0) + 1;

        $this->update(['failures' => $failures]);

        return $failures;
    }

    public function reset(): void
    {
        Session::forget(self::KEY);
    }

    /**
     * A half-finished sign-in should not sit in a session all afternoon.
     */
    public function hasExpired(): bool
    {
        $startedAt = $this->state()['started_at'] ?? null;

        if ($startedAt === null) {
            return true;
        }

        return Carbon::createFromTimestamp($startedAt)
            ->addMinutes(self::TTL_MINUTES)
            ->isPast();
    }

    /**
     * The route a half-finished flow should be sent back to.
     */
    public function routeForStage(): string
    {
        return match ($this->stage()) {
            self::STAGE_PASSWORD => 'login.password',
            self::STAGE_DOB => 'login.dob',
            self::STAGE_CODE => 'login.code',
            default => 'login',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function state(): array
    {
        return Session::get(self::KEY, []);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function update(array $changes): void
    {
        Session::put(self::KEY, array_merge($this->state(), $changes));
    }
}
