<?php

namespace App\Mail;

use App\Models\User;
use App\Services\CommissionBreakdown;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Spec section 6.1: "Weekly summary -- a Sunday recap of how my code did."
 */
class WeeklySummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly CommissionBreakdown $summary,
        public readonly string $weekLabel,
        public readonly ?string $encouragement = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your week on Pitch Innovations: {$this->weekLabel}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.weekly-summary',
            with: [
                'name' => $this->user->firstName(),
                'summary' => $this->summary,
                'weekLabel' => $this->weekLabel,
                'encouragement' => $this->encouragement,
                'dashboardUrl' => route('dashboard'),
            ],
        );
    }
}
