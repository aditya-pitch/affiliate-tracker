# Affiliate Coupon Dashboard

A private, login-protected dashboard where Pitch Innovations affiliate creators
track the sales driven by their personal coupon codes — and the commission they
earn — in real time, through to getting paid.

Built to the functional specification in `Affiliate-Dashboard-Spec.docx`.
PHP 8.2+ / Laravel 12 / MySQL.

---

## Getting it running

The machine this was written on had no PHP, Composer or Node, so nothing here
has been executed yet — see [Status](#status) below. These are the steps to
bring it up.

**1. Install dependencies**

```bash
composer install
```

**2. Create your environment file**

```bash
cp .env.example .env && php artisan key:generate
```

Then set `DB_*` for your MySQL database, and `MAIL_*` for real SMTP
credentials. Mail is not optional: sign-in one-time codes go out by email, so
nobody can log in until mail works. For local development leave
`MAIL_MAILER=log` and read the codes out of `storage/logs/laravel.log`.

**3. Create the schema and some data to look at**

```bash
php artisan migrate --seed
```

The seeder creates an admin, three creators, and three sales — one running right
now, one ended and paid, one ended and awaiting an invoice — with orders in
seven currencies and a few refunds. It prints the sign-in details when it
finishes.

**4. Run it**

```bash
php artisan serve
```

**5. Add the scheduler**

One cron entry drives closing ended sales, digests and weekly summaries:

```bash
* * * * * cd /path/to/affiliate-tracker && php artisan schedule:run >> /dev/null 2>&1
```

There is no frontend build step. The CSS and JS are plain files in `public/assets`,
so there is no Node dependency and nothing to compile on deploy.

---

## How it is put together

| Area | Where |
|:--|:--|
| Commission maths (spec 5.5) | `app/Services/CommissionCalculator.php` |
| Per-sale summary (spec 5.2) | `app/Services/SaleSummaryService.php` |
| Orders table + masking (spec 5.3) | `app/Services/OrderTable.php` |
| Four-step sign-in (spec 3) | `app/Http/Controllers/Auth/LoginController.php`, `app/Services/LoginFlow.php`, `app/Services/OtpService.php` |
| Live updates (spec 5.6) | `DashboardController::live()` + `public/assets/js/dashboard.js` |
| Settlement, invoice, payment (spec 5.7) | `app/Services/SettlementService.php` |
| Excel report (spec 5.7) | `app/Services/SaleReportExport.php` |
| Encouragement messages (spec 1) | `app/Services/EncouragementService.php` |
| Business rules you may want to tune | `config/affiliate.php` |

### The commission calculation

Spec section 5.5, which is easy to get subtly wrong in two places:

```
A          = customer payment ÷ 1.18     (remove 18% GST first)
commission = A × the creator's own rate
fee        = A × 5%                      (on A, not on the commission)
payout     = commission − fee
```

The spec's worked example — ₹10,000 at 15% giving a ₹847.46 payout — is asserted
figure-for-figure in `tests/Unit/CommissionCalculatorTest.php`. If that test ever
fails, creators are being paid the wrong amount.

Commission rates are per creator (`affiliate_profiles.commission_rate`) and are
never hard-coded.

### Privacy and access

- Creators only ever see their own rows: every query is scoped by `user_id`, and
  opening a sale you took no part in returns 404 rather than 403, so slugs cannot
  be probed.
- Order references show only their last 2 characters; customer surnames are
  replaced with `XX`; customer email addresses are never sent to the browser.
- Sessions time out after 20 minutes of genuine inactivity. The 5-second polling
  deliberately does **not** count as activity — otherwise an abandoned dashboard
  would stay signed in forever.
- Sign-in, payouts and password changes are written to `storage/logs/audit.log`,
  kept for a year.

### The tone of the encouragement messages

The brief was firm about this, so it is worth repeating outside the code: the
nudges must never demotivate. There is no "your code is underperforming" bucket
in `EncouragementService`, and a quiet hour is framed as an opportunity to post
rather than a failure. If you add messages, every line has to read well to a
creator having a slow day.

---

## Decisions taken

Things the spec left open, and what was done:

| Question | Decision |
|:--|:--|
| Live updates: polling or websockets? | **Polling** every 5 seconds against a JSON endpoint. Meets "within a few seconds", needs no long-running process or Node, runs on ordinary PHP hosting. Swapping in Reverb later means changing `dashboard.js` and the `/live` endpoint only. |
| Multiple coupon codes (spec 8) | All of a creator's codes **roll up** into one set of totals; the Code column shows which code was used per order. |
| Batched digests (spec 6.1) | **Offered.** Creators choose Immediate / Hourly / Daily in Settings. |
| Customer detail visible to affiliates (spec 8) | Masked first name + `XX`, country and state. Email addresses **never** shown. |
| Report download timing | Restricted to **ended** sales, following the placement in spec 5.7. Easy to relax if you would rather creators could export mid-sale. |
| Who gets a settlement | Creators with at least one **non-refunded** order. A creator whose orders were all refunded gets no report and no email. |
| Report locking | Summary figures are **snapshotted** into `settlements` at close. A late refund cannot silently change a report a creator has already invoiced against. |
| Exchange rates | Locked onto each order at creation (`orders.exchange_rate`). `app/Support/ExchangeRates.php` holds a static table as a placeholder — point it at whatever rate source the store uses when the checkout is wired up. |

### One inconsistency in the spec, for the record

Section 5.2's table describes affiliate commission as "decided percentage of
**gross** earnings, minus transaction fees and gst", while section 5.5 gives an
explicit formula and worked example in which commission is a percentage of the
value **excluding** GST. The two produce different payouts. Section 5.5 is
implemented, since it is the one with the arithmetic. Worth a sentence in the
next revision of the doc.

Section 5.2's example figures are also internally inconsistent (₹51,857.55 is
25% of the ₹2,07,430.20 gross, not 15%), so they were treated as illustrative.

---

## What is deliberately not here

**The checkout integration (spec section 7).** Deferred by agreement — "ignore
that part, all the linking part we'll do later". The schema, the settlement
flow, and the dashboard are all complete and work against seeded data; what is
missing is the piece that feeds real orders in.

When you come to wire it up, the seam is small: create `Order` records (through
the model, not a bulk insert, so `OrderObserver` fires the per-order emails)
with the coupon code resolved to a `coupon_code_id`, the sale resolved by
`placed_at` falling inside a sale's window, and the exchange rate locked at that
moment. Refunds set `is_refunded` and `refunded_at`. Nothing else needs to change.

The admin side is intentionally minimal — the spec puts it out of scope except
for the payment control in 5.7, so `/admin/settlements` does exactly that: see
who is owed what, read their invoice, record the payment.

---

## Tests

```bash
php artisan test
```

Covering the things that would actually hurt: the commission arithmetic against
the spec's worked example, refunds excluded from earnings, multi-code roll-up,
locked exchange rates, one creator never seeing another's data, invoice upload
refused while a sale is live, reports frozen at close, and a sign-in whose steps
cannot be skipped and whose emailed code cannot be brute-forced.

---

## Status

Verified end to end against PHP 8.4 / Laravel 12 on SQLite:

- `composer install`, `migrate --seed` and `php artisan test` all run clean —
  **39 tests, 134 assertions passing.**
- Signed in through all four steps, including reading the emailed one-time code,
  and landed on the live sale.
- Confirmed live updating: an order inserted while the dashboard sat open
  appeared at the top of the table on its own, with units, gross and payout all
  moving and no page refresh.
- Downloaded the Excel report and confirmed it is a valid `.xlsx` carrying the
  locked summary figures and the masked orders list.
- Uploaded an invoice, recorded the payment as an admin, and confirmed the
  status flipped to Paid and the confirmation email rendered and sent.
- Ran all three scheduled commands (`sales:close-ended`,
  `affiliates:send-digests`, `affiliates:send-weekly-summaries`) successfully.

It has **not** been run against MySQL yet — the schema is plain enough that it
should be uneventful, but that is the one environment difference worth checking
before you deploy.
