<?php

namespace Tests\Unit;

use App\Support\Money;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    /**
     * The spec writes its own example as ₹2,07,430.20 — Indian grouping, not
     * the Western 207,430.20. Creators will read these figures next to their
     * own bank statements, so the convention has to match.
     */
    public function test_inr_uses_indian_digit_grouping(): void
    {
        $this->assertSame('₹2,07,430.20', Money::format(207430.20, 'INR'));
        $this->assertSame('₹1,00,000.00', Money::format(100000, 'INR'));
        $this->assertSame('₹1,00,00,000.00', Money::format(10000000, 'INR'));
        $this->assertSame('₹9,999.00', Money::format(9999, 'INR'));
        $this->assertSame('₹999.50', Money::format(999.5, 'INR'));
    }

    public function test_other_currencies_use_standard_grouping(): void
    {
        $this->assertSame('$207,430.20', Money::format(207430.20, 'USD'));
        $this->assertSame('€1,250.00', Money::format(1250, 'EUR'));
        $this->assertSame('£99.99', Money::format(99.99, 'GBP'));
    }

    public function test_negative_amounts_keep_the_sign_outside_the_symbol(): void
    {
        $this->assertSame('-₹1,525.42', Money::format(-1525.42, 'INR'));
    }

    public function test_an_unknown_currency_falls_back_to_its_code(): void
    {
        $this->assertSame('NZD 42.00', Money::format(42, 'NZD'));
    }
}
