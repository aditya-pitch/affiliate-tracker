<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * An operational update to our own team.
 *
 * One mailable for all of them rather than a class per event: these are short
 * internal notes with the same shape — a headline, a handful of facts, and a
 * link into the dashboard.
 */
class AdminAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, string>  $facts
     */
    public function __construct(
        public readonly string $headline,
        public readonly array $facts,
        public readonly ?string $actionUrl = null,
        public readonly ?string $actionLabel = null,
        public readonly ?string $subjectLine = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine ?? $this->headline,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.admin-alert',
            with: [
                'headline' => $this->headline,
                'facts' => $this->facts,
                'actionUrl' => $this->actionUrl,
                'actionLabel' => $this->actionLabel ?? 'Open the dashboard',
            ],
        );
    }
}
