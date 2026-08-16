<?php

namespace Database\Seeders;

use App\Models\AffiliateProfile;
use App\Models\CouponCode;
use App\Models\Order;
use App\Models\Sale;
use App\Models\Settlement;
use App\Models\User;
use App\Services\SettlementService;
use App\Support\ExchangeRates;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Demo data for developing against and for showing the dashboard to the team.
 *
 * Deliberately covers the cases that are easy to get wrong: a creator with two
 * coupon codes, a creator paid in USD rather than INR, orders in seven
 * currencies, refunds, a sale that is live right now, a sale that has ended and
 * been paid, and a sale that has ended and is waiting on an invoice.
 *
 * The checkout integration (spec section 7) is deferred, so these orders are
 * generated rather than imported.
 */
class DemoDataSeeder extends Seeder
{
    /** Real Pitch Innovations products. */
    private const PLUGINS = [
        'Loop2Kit',
        'Sonic Atlas',
        'Fluid Chords 2',
        'Eternal Arps',
        'Groove Shaper',
        'Fluid Pitch',
        'Rhythm Box',
        'Crystal Snow',
    ];

    /** country => [state, currency] */
    private const MARKETS = [
        'India' => [['Maharashtra', 'Karnataka', 'Delhi', 'Tamil Nadu', 'West Bengal', 'Gujarat', 'Kerala'], 'INR'],
        'United States' => [['California', 'New York', 'Texas', 'Washington', 'Illinois'], 'USD'],
        'United Kingdom' => [['England', 'Scotland', 'Wales'], 'GBP'],
        'Germany' => [['Bavaria', 'Berlin', 'Hesse'], 'EUR'],
        'Netherlands' => [['North Holland', 'Utrecht'], 'EUR'],
        'Australia' => [['New South Wales', 'Victoria', 'Queensland'], 'AUD'],
        'Canada' => [['Ontario', 'Quebec', 'British Columbia'], 'CAD'],
        'Singapore' => [['Central'], 'SGD'],
    ];

    private const FIRST_NAMES = [
        'Aditi', 'Rohan', 'Meera', 'Vikram', 'Ananya', 'Kabir', 'Isha', 'Arjun',
        'James', 'Sophie', 'Liam', 'Emma', 'Noah', 'Olivia', 'Lucas', 'Mia',
        'Tobias', 'Elena', 'Hiroshi', 'Clara', 'Daniel', 'Priya', 'Sanjay', 'Nina',
    ];

    private const LAST_NAMES = [
        'Raghunathan', 'Mehta', 'Chatterjee', 'Nair', 'Kulkarni', 'Bose',
        'Whitfield', 'Andersen', 'Okafor', 'Moreau', 'Rossi', 'Tanaka',
        'Fernandes', 'Hughes', 'Novak', 'Silva',
    ];

    private int $orderCounter = 1;

    public function run(): void
    {
        $password = env('DEMO_PASSWORD', 'password');

        $admin = $this->createAdmin($password);
        $affiliates = $this->createAffiliates($password);
        $sales = $this->createSales();

        // --- Orders -------------------------------------------------------

        // Black Friday 2025: everyone did well, one refund.
        $this->seedOrders($sales['black_friday'], $affiliates['aarav'], 34, refunds: 1);
        $this->seedOrders($sales['black_friday'], $affiliates['ritika'], 19, refunds: 0);
        $this->seedOrders($sales['black_friday'], $affiliates['marco'], 27, refunds: 2);

        // Loop2Kit intro: smaller campaign, Loop2Kit only.
        $this->seedOrders($sales['loop2kit'], $affiliates['aarav'], 12, refunds: 0, plugin: 'Loop2Kit');
        $this->seedOrders($sales['loop2kit'], $affiliates['marco'], 8, refunds: 1, plugin: 'Loop2Kit');

        // Summer Sale 2026 is running right now. Orders are spread across the
        // days since it opened, with a cluster in the last hour so the live
        // dashboard has something moving on it.
        $this->seedOrders($sales['summer'], $affiliates['aarav'], 23, refunds: 1, live: true);
        $this->seedOrders($sales['summer'], $affiliates['ritika'], 6, refunds: 0, live: true);
        $this->seedOrders($sales['summer'], $affiliates['marco'], 14, refunds: 0, live: true);

        // --- Settlement -----------------------------------------------------

        /** @var SettlementService $settlements */
        $settlements = app(SettlementService::class);

        // Close the two ended sales. Emails are suppressed: seeding should not
        // fire mail at whatever address MAIL_MAILER happens to point at.
        $settlements->closeSale($sales['black_friday'], notify: false);
        $settlements->closeSale($sales['loop2kit'], notify: false);

        // Black Friday is fully settled: invoices in, commissions paid.
        foreach (Settlement::where('sale_id', $sales['black_friday']->id)->get() as $settlement) {
            $settlement->forceFill([
                'status' => Settlement::STATUS_PAID,
                'invoice_original_name' => 'invoice-black-friday-2025.pdf',
                'invoice_path' => null, // no real file on disk in demo data
                'invoice_uploaded_at' => Carbon::parse('2025-12-03 11:20'),
                'paid_amount' => $settlement->payout_amount,
                'paid_on' => Carbon::parse('2025-12-08'),
                'payment_reference' => 'NEFT/'.str_pad((string) $settlement->id, 6, '0', STR_PAD_LEFT),
                'paid_by_user_id' => $admin->id,
                'paid_at' => Carbon::parse('2025-12-08 15:42'),
            ])->save();
        }

        // Loop2Kit is mid-settlement: one creator has invoiced, one has not.
        $loop2kitSettlements = Settlement::where('sale_id', $sales['loop2kit']->id)->orderBy('id')->get();
        if ($first = $loop2kitSettlements->first()) {
            $first->forceFill([
                'status' => Settlement::STATUS_INVOICE_UPLOADED,
                'invoice_original_name' => 'invoice-loop2kit-intro.pdf',
                'invoice_uploaded_at' => Carbon::parse('2026-04-22 09:05'),
            ])->save();
        }

        $this->command?->newLine();
        $this->command?->info('Demo data seeded.');
        $this->command?->line("  Admin      admin@pitchinnovations.com / {$password}");
        $this->command?->line("  Affiliate  aarav@example.com / {$password}   (DOB 1994-03-12, INR, 15%, two codes)");
        $this->command?->line("  Affiliate  ritika@example.com / {$password}  (DOB 1998-11-02, INR, 12.5%)");
        $this->command?->line("  Affiliate  marco@example.com / {$password}   (DOB 1991-07-25, USD, 20%)");
        $this->command?->newLine();
        $this->command?->line('  Sign-in codes are emailed. With MAIL_MAILER=log, read them from storage/logs/laravel.log.');
    }

    private function createAdmin(string $password): User
    {
        return User::create([
            'name' => 'Pitch Innovations Team',
            'email' => 'admin@pitchinnovations.com',
            'password' => Hash::make($password),
            'date_of_birth' => '1990-01-01',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, User>
     */
    private function createAffiliates(string $password): array
    {
        $definitions = [
            'aarav' => [
                'name' => 'Aarav Menon',
                'email' => 'aarav@example.com',
                'dob' => '1994-03-12',
                'rate' => 0.1500,
                'currency' => 'INR',
                'country' => 'IN',
                'codes' => ['AARAV15', 'AARAVSUMMER'],
                'frequency' => 'immediate',
            ],
            'ritika' => [
                'name' => 'Ritika Shah',
                'email' => 'ritika@example.com',
                'dob' => '1998-11-02',
                'rate' => 0.1250,
                'currency' => 'INR',
                'country' => 'IN',
                'codes' => ['RITIKA'],
                'frequency' => 'daily',
            ],
            'marco' => [
                'name' => 'Marco Bellini',
                'email' => 'marco@example.com',
                'dob' => '1991-07-25',
                'rate' => 0.2000,
                'currency' => 'USD',
                'country' => 'IT',
                'codes' => ['MARCO20'],
                'frequency' => 'hourly',
            ],
        ];

        $affiliates = [];

        foreach ($definitions as $key => $definition) {
            $user = User::create([
                'name' => $definition['name'],
                'email' => $definition['email'],
                'password' => Hash::make($password),
                'date_of_birth' => $definition['dob'],
                'role' => User::ROLE_AFFILIATE,
                'is_active' => true,
            ]);

            AffiliateProfile::create([
                'user_id' => $user->id,
                'display_name' => $definition['name'],
                'commission_rate' => $definition['rate'],
                'payout_currency' => $definition['currency'],
                'country_code' => $definition['country'],
                'payout_account_name' => $definition['name'],
                'notify_master' => true,
                'notify_on_sale' => true,
                'notify_weekly_summary' => true,
                'sale_notification_frequency' => $definition['frequency'],
            ]);

            foreach ($definition['codes'] as $code) {
                CouponCode::create([
                    'user_id' => $user->id,
                    'code' => $code,
                    'is_active' => true,
                ]);
            }

            $affiliates[$key] = $user->fresh(['profile', 'couponCodes']);
        }

        return $affiliates;
    }

    /**
     * @return array<string, Sale>
     */
    private function createSales(): array
    {
        $now = Carbon::now();

        return [
            'black_friday' => Sale::create([
                'name' => 'Black Friday Sale 2025',
                'slug' => 'black-friday-2025',
                'description' => 'Sitewide Black Friday campaign across the full plugin range.',
                'starts_at' => Carbon::parse('2025-11-24 00:00'),
                'ends_at' => Carbon::parse('2025-12-01 23:59'),
            ]),

            'loop2kit' => Sale::create([
                'name' => 'Loop2Kit Intro Sale',
                'slug' => 'loop2kit-intro',
                'description' => 'Launch pricing for Loop2Kit.',
                'starts_at' => Carbon::parse('2026-04-10 00:00'),
                'ends_at' => Carbon::parse('2026-04-20 23:59'),
            ]),

            // Running right now, so the dashboard opens on a live sale.
            'summer' => Sale::create([
                'name' => 'Summer Sale 2026',
                'slug' => 'summer-2026',
                'description' => 'Summer campaign across all plugins.',
                'starts_at' => $now->copy()->subDays(3)->startOfDay(),
                'ends_at' => $now->copy()->addDays(4)->endOfDay(),
            ]),
        ];
    }

    /**
     * Generate orders for one creator on one sale, spread across its run.
     */
    private function seedOrders(
        Sale $sale,
        User $affiliate,
        int $count,
        int $refunds = 0,
        ?string $plugin = null,
        bool $live = false,
    ): void {
        $codes = $affiliate->couponCodes;
        $payoutCurrency = $affiliate->payoutCurrency();

        $windowStart = $sale->starts_at->copy();
        $windowEnd = $live ? Carbon::now() : $sale->ends_at->copy();
        // Carbon 3 returns a signed difference, so the receiver must be the
        // earlier of the two for this to come out positive.
        $windowSeconds = max(1, (int) $windowStart->diffInSeconds($windowEnd));

        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            // The last few orders of a live sale land inside the past hour, so
            // the momentum messaging and the "just now" timestamps have
            // something real to describe.
            $placedAt = ($live && $i >= $count - 4)
                ? Carbon::now()->subMinutes(random_int(2, 55))
                : $windowStart->copy()->addSeconds(random_int(0, $windowSeconds));

            $country = array_rand(self::MARKETS);
            [$states, $currency] = self::MARKETS[$country];

            $amount = $this->priceFor($currency);
            $rate = ExchangeRates::rate($currency, $payoutCurrency);

            $isRefunded = $i < $refunds;

            $rows[] = [
                'sale_id' => $sale->id,
                'coupon_code_id' => $codes->random()->id,
                'user_id' => $affiliate->id,
                'order_ref' => sprintf('PI-%s-%05d', $sale->starts_at->format('Y'), $this->orderCounter++),
                'placed_at' => $placedAt,
                'customer_first_name' => self::FIRST_NAMES[array_rand(self::FIRST_NAMES)],
                'customer_last_name' => self::LAST_NAMES[array_rand(self::LAST_NAMES)],
                'customer_email' => 'customer'.$this->orderCounter.'@example.com',
                'country' => $country,
                'state' => $states[array_rand($states)],
                'plugin' => $plugin ?? self::PLUGINS[array_rand(self::PLUGINS)],
                'currency' => $currency,
                'amount' => $amount,
                'payout_currency' => $payoutCurrency,
                'exchange_rate' => $rate,
                'converted_amount' => round($amount * $rate, 2),
                'is_refunded' => $isRefunded,
                'refunded_at' => $isRefunded ? $placedAt->copy()->addDays(2) : null,
                'created_at' => $placedAt,
                'updated_at' => $placedAt,
            ];
        }

        Order::insert($rows);
    }

    /**
     * A believable plugin price in the given currency.
     */
    private function priceFor(string $currency): float
    {
        $usdPrices = [29, 39, 49, 59, 69, 79, 99];
        $usd = $usdPrices[array_rand($usdPrices)];

        if ($currency === 'INR') {
            // Indian pricing is set locally rather than converted from USD.
            $inrPrices = [1499, 1999, 2499, 2999, 3999, 4999, 6999];

            return (float) $inrPrices[array_rand($inrPrices)];
        }

        return round(ExchangeRates::convert((float) $usd, 'USD', $currency), 2);
    }
}
