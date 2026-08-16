<?php

namespace App\Support;

/**
 * Reference exchange rates.
 *
 * Spec sections 5.4 / 8: the rate is locked onto the order at the moment it is
 * placed, so historical totals never shift. That means this class is only ever
 * consulted once per order -- at creation -- and never again when a report is
 * rendered. Everything downstream reads orders.exchange_rate.
 *
 * The table below is a static placeholder. When the checkout integration is
 * wired up (spec section 7, deferred), replace rate() with a call to whichever
 * rate source the store already uses at the point of sale, so the dashboard and
 * the store agree on what a given order was worth.
 */
final class ExchangeRates
{
    /**
     * Value of one unit of each currency, expressed in INR.
     *
     * @var array<string, float>
     */
    private const IN_INR = [
        'INR' => 1.0,
        'USD' => 87.50,
        'EUR' => 95.20,
        'GBP' => 111.40,
        'AUD' => 57.10,
        'CAD' => 63.40,
        'SGD' => 65.30,
        'AED' => 23.82,
        'JPY' => 0.58,
    ];

    /**
     * How many units of $to one unit of $from is worth.
     */
    public static function rate(string $from, string $to): float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return 1.0;
        }

        $fromInInr = self::IN_INR[$from] ?? null;
        $toInInr = self::IN_INR[$to] ?? null;

        if ($fromInInr === null || $toInInr === null) {
            throw new \InvalidArgumentException("No exchange rate available for {$from} to {$to}.");
        }

        return round($fromInInr / $toInInr, 8);
    }

    /**
     * Convert an amount, returning the converted value rounded to 2dp.
     */
    public static function convert(float $amount, string $from, string $to): float
    {
        return Money::round($amount * self::rate($from, $to));
    }

    /**
     * @return list<string>
     */
    public static function supported(): array
    {
        return array_keys(self::IN_INR);
    }
}
