<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent by our team when a creator's dashboard is ready: their login details and
 * how to sign in.
 *
 * The password is passed in rather than read off the user, because by the time
 * it is on the user it is a hash. It is only ever available in the moment it
 * was generated, which is why this email is normally sent right after creating
 * the account or reissuing the password.
 */
class CreatorWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly ?string $password = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Pitch Innovations affiliate dashboard is ready',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.creator-welcome',
            with: [
                'name' => $this->user->firstName(),
                'email' => $this->user->email,
                'password' => $this->password,
                'dateOfBirth' => $this->user->date_of_birth?->format('j F Y'),
                'codes' => $this->user->couponCodes->pluck('code')->all(),
                'rate' => $this->user->profile->commissionRatePercent(),
                'currency' => $this->user->profile->payout_currency,
                'loginUrl' => route('login'),
            ],
        );
    }
}
