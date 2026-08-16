<?php

namespace Database\Factories;

use App\Models\AffiliateProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AffiliateProfile>
 */
class AffiliateProfileFactory extends Factory
{
    protected $model = AffiliateProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'display_name' => fake()->name(),
            'commission_rate' => 0.1500,
            'payout_currency' => 'INR',
            'country_code' => 'IN',
            'notify_master' => true,
            'notify_on_sale' => true,
            'notify_weekly_summary' => true,
            'sale_notification_frequency' => 'immediate',
        ];
    }

    public function rate(float $rate): static
    {
        return $this->state(fn () => ['commission_rate' => $rate]);
    }

    public function paidInUsd(): static
    {
        return $this->state(fn () => [
            'payout_currency' => 'USD',
            'country_code' => 'US',
        ]);
    }
}
