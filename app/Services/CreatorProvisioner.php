<?php

namespace App\Services;

use App\Mail\CreatorWelcomeMail;
use App\Models\AffiliateProfile;
use App\Models\CouponCode;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Setting a new creator up: account, profile, coupon codes, and the password
 * we issue them (spec section 3 -- "We issue the password").
 *
 * On passwords, since this is the part people expect to work differently:
 * the generated password is returned to the caller once, so it can be shown
 * on screen and put in the welcome email, and is then gone. What is stored is
 * a bcrypt hash, which cannot be reversed -- not by this application, not by
 * an admin, not by anyone who steals the database. If a creator loses their
 * password the answer is to issue a new one, not to look the old one up.
 *
 * That is a deliberate constraint rather than a missing feature. This dashboard
 * shows people what they are owed; a readable password store would put every
 * creator's account one database leak away from a stranger, and creators reuse
 * passwords elsewhere.
 */
final class CreatorProvisioner
{
    /**
     * Create a creator and everything their dashboard needs.
     *
     * @param  array<string, mixed>  $data
     * @return array{user: User, password: string}
     */
    public function create(array $data): array
    {
        $password = $this->generatePassword();

        $user = DB::transaction(function () use ($data, $password) {
            $user = User::create([
                'name' => $data['name'],
                'email' => strtolower(trim($data['email'])),
                'password' => $password,
                'date_of_birth' => $data['date_of_birth'],
                'role' => User::ROLE_AFFILIATE,
                'is_active' => true,
            ]);

            $user->forceFill(['password_issued_at' => Carbon::now()])->save();

            AffiliateProfile::create([
                'user_id' => $user->id,
                'display_name' => $data['display_name'] ?? $data['name'],
                'commission_rate' => $data['commission_rate'],
                'payout_currency' => $data['payout_currency'],
                'country_code' => $data['country_code'] ?? ($data['payout_currency'] === 'INR' ? 'IN' : 'US'),
                'payout_account_name' => $data['payout_account_name'] ?? null,
                'payout_details' => $data['payout_details'] ?? null,
                'gst_number' => $data['gst_number'] ?? null,
                'pan_number' => $data['pan_number'] ?? null,
                'notify_master' => true,
                'notify_on_sale' => true,
                'notify_weekly_summary' => true,
                'sale_notification_frequency' => $data['sale_notification_frequency'] ?? 'immediate',
            ]);

            foreach ($this->normaliseCodes($data['codes'] ?? []) as $code) {
                CouponCode::create([
                    'user_id' => $user->id,
                    'code' => $code,
                    'is_active' => true,
                ]);
            }

            return $user;
        });

        Log::channel('audit')->info('Creator provisioned', [
            'user_id' => $user->id,
            'email' => $user->email,
            'rate' => $data['commission_rate'],
            'currency' => $data['payout_currency'],
        ]);

        return ['user' => $user->fresh(['profile', 'couponCodes']), 'password' => $password];
    }

    /**
     * Issue a fresh password. Returned once for display, then unrecoverable.
     */
    public function resetPassword(User $user): string
    {
        $password = $this->generatePassword();

        $user->forceFill([
            'password' => $password,
            'password_issued_at' => Carbon::now(),
            'remember_token' => Str::random(60),
        ])->save();

        Log::channel('audit')->info('Creator password reissued by admin', [
            'user_id' => $user->id,
            'by' => auth()->id(),
        ]);

        return $password;
    }

    /**
     * Email the creator their login details and how to sign in.
     *
     * The password is only included when one has just been generated -- there
     * is no way to put it in a later email, because by then it is only a hash.
     */
    public function sendWelcome(User $user, ?string $password = null): void
    {
        $user->loadMissing('profile', 'couponCodes');

        Mail::to($user->email)->send(new CreatorWelcomeMail($user, $password));

        $user->forceFill(['welcome_sent_at' => Carbon::now()])->save();

        Log::channel('audit')->info('Welcome email sent', [
            'user_id' => $user->id,
            'included_password' => $password !== null,
            'by' => auth()->id(),
        ]);
    }

    public function addCode(User $user, string $code): CouponCode
    {
        return CouponCode::create([
            'user_id' => $user->id,
            'code' => $this->normaliseCode($code),
            'is_active' => true,
        ]);
    }

    /**
     * A readable but strong password: three words, a number and a symbol.
     *
     * Readable matters here because a human reads it off a screen and types it
     * into an email, or a creator types it from their phone. A wall of random
     * characters gets mistyped, then reset, then written on a sticky note.
     */
    public function generatePassword(): string
    {
        $words = [
            'amber', 'basalt', 'cinder', 'delta', 'ember', 'fable', 'granite', 'harbour',
            'indigo', 'juniper', 'kestrel', 'lantern', 'marble', 'nectar', 'onyx', 'prism',
            'quartz', 'ripple', 'saffron', 'tundra', 'umber', 'velvet', 'willow', 'zenith',
        ];

        $pick = fn () => $words[random_int(0, count($words) - 1)];

        return sprintf(
            '%s-%s-%s%d%s',
            $pick(),
            $pick(),
            $pick(),
            random_int(10, 99),
            ['!', '#', '@', '?'][random_int(0, 3)]
        );
    }

    /**
     * @param  list<string>|string  $codes
     * @return list<string>
     */
    private function normaliseCodes(array|string $codes): array
    {
        if (is_string($codes)) {
            $codes = preg_split('/[\s,]+/', $codes) ?: [];
        }

        return collect($codes)
            ->filter(fn ($code) => trim((string) $code) !== '')
            ->map(fn ($code) => $this->normaliseCode($code))
            ->unique()
            ->values()
            ->all();
    }

    private function normaliseCode(string $code): string
    {
        return Str::upper(preg_replace('/[^A-Za-z0-9_-]/', '', trim($code)));
    }
}
