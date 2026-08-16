<?php

namespace App\Mail;

use App\Models\Order;
use App\Services\CommissionCalculator;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Spec section 6.1: "Coupon code sales -- email me every time someone buys with
 * my code." Only sent when the creator has both the master switch and this
 * switch on, and has chosen immediate rather than a digest.
 */
class OrderPlacedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Order $order,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New sale on your code {$this->order->couponCode->code}",
        );
    }

    public function content(): Content
    {
        $user = $this->order->user;
        $earned = CommissionCalculator::fromConfig()->forSingleOrder(
            (float) $this->order->converted_amount,
            $user->commissionRate(),
        );

        return new Content(
            markdown: 'emails.order-placed',
            with: [
                'name' => $user->firstName(),
                'order' => $this->order,
                'plugin' => $this->order->plugin,
                'code' => $this->order->couponCode->code,
                'country' => $this->order->country,
                'earned' => Money::format($earned, $user->payoutCurrency()),
                'dashboardUrl' => route('sales.show', $this->order->sale),
            ],
        );
    }
}
