<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoginCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $code,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->code} is your Pitch Innovations sign-in code",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.login-code',
            with: [
                'name' => $this->user->firstName(),
                'code' => $this->code,
                'minutes' => (int) config('affiliate.otp.ttl_minutes', 10),
            ],
        );
    }
}
