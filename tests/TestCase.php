<?php

namespace Tests;

use App\Models\AffiliateProfile;
use App\Models\CouponCode;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Mail;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Creating an order fires OrderObserver, which emails creators who have
        // asked for per-order notifications. Faked by default so tests that are
        // not about email neither send nor spend time rendering them.
        Mail::fake();
    }

    /**
     * A ready-to-use affiliate: user, profile and one coupon code.
     */
    protected function makeAffiliate(
        float $commissionRate = 0.15,
        string $payoutCurrency = 'INR',
        string $code = 'TESTCODE',
        array $profileAttributes = [],
    ): User {
        $user = User::factory()->create();

        AffiliateProfile::factory()->create(array_merge([
            'user_id' => $user->id,
            'commission_rate' => $commissionRate,
            'payout_currency' => $payoutCurrency,
        ], $profileAttributes));

        CouponCode::factory()->create([
            'user_id' => $user->id,
            'code' => $code,
        ]);

        return $user->fresh(['profile', 'couponCodes']);
    }
}
