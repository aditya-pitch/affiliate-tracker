<?php

namespace App\Mail;

use App\Models\Settlement;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Spec sections 5.7 / 6.2: sent when our team marks a commission as paid.
 * Always sent, regardless of activity switches.
 */
class PaymentConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Settlement $settlement,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your {$this->settlement->sale->name} commission has been paid",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.payment-confirmation',
            with: [
                'name' => $this->settlement->user->firstName(),
                'sale' => $this->settlement->sale,
                'settlement' => $this->settlement,
                'amount' => Money::format(
                    $this->settlement->paid_amount ?? $this->settlement->payout_amount,
                    $this->settlement->currency
                ),
                'paidOn' => $this->settlement->paid_on?->format('j F Y'),
                'reference' => $this->settlement->payment_reference,
                'reportUrl' => route('sales.show', $this->settlement->sale),
            ],
        );
    }
}
