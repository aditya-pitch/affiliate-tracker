<?php

namespace App\Mail;

use App\Models\User;
use App\Services\CommissionCalculator;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The batched alternative to one email per order.
 *
 * Spec section 6.1: "For very high-volume sales, consider offering a batched
 * digest (hourly or daily) instead of one email per order." Confirmed during
 * spec review, and selectable per creator in Settings.
 *
 * @property Collection<int, \App\Models\Order> $orders
 */
class OrderDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly Collection $orders,
        public readonly string $period,
    ) {}

    public function envelope(): Envelope
    {
        $count = $this->orders->count();
        $noun = $count === 1 ? 'sale' : 'sales';

        return new Envelope(
            subject: "{$count} new {$noun} on your code",
        );
    }

    public function content(): Content
    {
        $currency = $this->user->payoutCurrency();
        $calculator = CommissionCalculator::fromConfig();

        $gross = (float) $this->orders->sum('converted_amount');
        $earned = $calculator->forSingleOrder($gross, $this->user->commissionRate());

        return new Content(
            markdown: 'emails.order-digest',
            with: [
                'name' => $this->user->firstName(),
                'orders' => $this->orders,
                'count' => $this->orders->count(),
                'period' => $this->period,
                'earned' => Money::format($earned, $currency),
                'currency' => $currency,
                'dashboardUrl' => route('dashboard'),
            ],
        );
    }
}
