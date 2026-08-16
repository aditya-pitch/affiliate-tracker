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
 * Spec sections 5.7 / 6.2: sent when a sale the creator took part in closes,
 * linking to the final report. Always sent, regardless of activity switches.
 */
class SaleEndedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Settlement $settlement,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your {$this->settlement->sale->name} report is ready",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.sale-ended',
            with: [
                'name' => $this->settlement->user->firstName(),
                'sale' => $this->settlement->sale,
                'settlement' => $this->settlement,
                'unitsSold' => $this->settlement->units_sold,
                'payout' => Money::format($this->settlement->payout_amount, $this->settlement->currency),
                'reportUrl' => route('sales.show', $this->settlement->sale),
            ],
        );
    }
}
