<?php

namespace Database\Factories;

use App\Models\CouponCode;
use App\Models\Order;
use App\Models\Sale;
use App\Models\User;
use App\Support\ExchangeRates;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'coupon_code_id' => CouponCode::factory(),
            'user_id' => User::factory(),
            'order_ref' => 'PI-2026-'.fake()->unique()->numerify('#####'),
            'placed_at' => Carbon::now()->subHours(fake()->numberBetween(1, 48)),
            'customer_first_name' => fake()->firstName(),
            'customer_last_name' => fake()->lastName(),
            'customer_email' => fake()->unique()->safeEmail(),
            'country' => 'India',
            'state' => 'Maharashtra',
            'plugin' => 'Loop2Kit',
            'currency' => 'INR',
            'amount' => 2999.00,
            'payout_currency' => 'INR',
            'exchange_rate' => 1,
            'converted_amount' => 2999.00,
            'is_refunded' => false,
            'refunded_at' => null,
        ];
    }

    /**
     * An order paid in a currency other than the creator's payout currency,
     * with the rate locked at order time the way the real thing does it.
     */
    public function paidIn(string $currency, float $amount, string $payoutCurrency = 'INR'): static
    {
        $rate = ExchangeRates::rate($currency, $payoutCurrency);

        return $this->state(fn () => [
            'currency' => $currency,
            'amount' => $amount,
            'payout_currency' => $payoutCurrency,
            'exchange_rate' => $rate,
            'converted_amount' => round($amount * $rate, 2),
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn () => [
            'is_refunded' => true,
            'refunded_at' => Carbon::now()->subHour(),
        ]);
    }
}
