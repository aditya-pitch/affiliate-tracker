<?php

namespace App\Support;

/**
 * Money formatting for the dashboard.
 *
 * Written by hand rather than leaning on ext-intl, which is not guaranteed to
 * be present on shared PHP hosting, and because INR needs the Indian grouping
 * convention the spec uses in its own examples -- 2,07,430.20 rather than
 * 207,430.20.
 */
final class Money
{
    private const SYMBOLS = [
        'INR' => '₹',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'AUD' => 'A$',
        'CAD' => 'C$',
        'JPY' => '¥',
        'SGD' => 'S$',
        'AED' => 'AED ',
    ];

    /**
     * Format an amount with its currency symbol, e.g. ₹2,07,430.20.
     */
    public static function format(float|string $amount, string $currency): string
    {
        $amount = (float) $amount;
        $symbol = self::symbol($currency);
        $negative = $amount < 0;

        $formatted = $currency === 'INR'
            ? self::indianGrouping(abs($amount))
            : number_format(abs($amount), 2);

        return ($negative ? '-' : '').$symbol.$formatted;
    }

    /**
     * Format without the symbol -- used in the Excel export, where the column
     * carries the currency and repeating the symbol in every cell makes the
     * numbers harder to scan.
     */
    public static function plain(float|string $amount, string $currency): string
    {
        $amount = (float) $amount;

        return $currency === 'INR'
            ? ($amount < 0 ? '-' : '').self::indianGrouping(abs($amount))
            : number_format($amount, 2);
    }

    public static function symbol(string $currency): string
    {
        return self::SYMBOLS[strtoupper($currency)] ?? strtoupper($currency).' ';
    }

    /**
     * 207430.2 -> "2,07,430.20"
     *
     * The last three digits group normally; everything above them groups in
     * twos (thousand, lakh, crore).
     */
    private static function indianGrouping(float $amount): string
    {
        $fixed = number_format($amount, 2, '.', '');
        [$whole, $decimals] = explode('.', $fixed);

        if (strlen($whole) > 3) {
            $lastThree = substr($whole, -3);
            $rest = substr($whole, 0, -3);
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $whole = $rest.','.$lastThree;
        }

        return $whole.'.'.$decimals;
    }

    /**
     * Round to two decimal places the way money should be rounded. Every step
     * of the commission calculation rounds here so the figures a creator adds
     * up by hand match the figures on screen.
     */
    public static function round(float $amount): float
    {
        return round($amount, 2);
    }
}
