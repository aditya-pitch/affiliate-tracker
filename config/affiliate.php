<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Commission maths
    |--------------------------------------------------------------------------
    |
    | Spec section 5.5. Commission is never calculated on the amount the
    | customer paid; GST is removed first to arrive at the sale value
    | excluding GST (called "A" in the spec), and both the affiliate's
    | commission and the transaction fee are calculated on A:
    |
    |     A       = customer payment / (1 + gst_rate)
    |     gst     = customer payment - A
    |     commission = A * (affiliate's own rate)
    |     fee        = A * transaction_fee_rate
    |     payout     = commission - fee
    |
    | The commission rate is deliberately NOT configured here: it is set per
    | affiliate on affiliate_profiles.commission_rate.
    |
    */

    'gst_rate' => (float) env('AFFILIATE_GST_RATE', 0.18),

    'transaction_fee_rate' => (float) env('AFFILIATE_TRANSACTION_FEE_RATE', 0.05),

    /*
    |--------------------------------------------------------------------------
    | Payout currencies
    |--------------------------------------------------------------------------
    |
    | Spec section 5.4: summary figures are converted to INR for Indian
    | creators and USD for creators based abroad. Individual order rows always
    | keep the currency the customer actually paid in.
    |
    */

    'payout_currencies' => ['INR', 'USD'],

    'default_payout_currency' => 'INR',

    /*
    |--------------------------------------------------------------------------
    | Sign-in one-time code
    |--------------------------------------------------------------------------
    |
    | Spec section 3. The emailed code is the step that actually secures the
    | account, so it is always required and cannot be disabled per user.
    |
    */

    'otp' => [
        'length' => 6,
        'ttl_minutes' => (int) env('AFFILIATE_OTP_TTL_MINUTES', 10),
        'max_attempts' => (int) env('AFFILIATE_OTP_MAX_ATTEMPTS', 5),
        'resend_cooldown_seconds' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Session idle timeout
    |--------------------------------------------------------------------------
    |
    | Spec section 3 / 9: financial pages time out after inactivity.
    |
    */

    'idle_timeout_minutes' => (int) env('AFFILIATE_IDLE_TIMEOUT_MINUTES', 20),

    /*
    |--------------------------------------------------------------------------
    | Live updates
    |--------------------------------------------------------------------------
    |
    | Spec section 5.6 asks for updates "within a few seconds, without the
    | creator refreshing the page". This is served by polling a JSON endpoint
    | rather than websockets, so the app runs on ordinary PHP hosting with no
    | long-running process. See DashboardController::live().
    |
    */

    'poll_seconds' => (int) env('AFFILIATE_POLL_SECONDS', 5),

    /*
    |--------------------------------------------------------------------------
    | Privacy masking
    |--------------------------------------------------------------------------
    |
    | Spec sections 5.3 / 8 / 9. Order references show only their last N
    | characters; customer surnames are replaced entirely; customer email
    | addresses are never exposed to affiliates.
    |
    */

    'masking' => [
        'order_ref_visible_chars' => 2,
        'order_ref_mask_char' => 'X',
        'surname_mask' => 'XX',
    ],

];
