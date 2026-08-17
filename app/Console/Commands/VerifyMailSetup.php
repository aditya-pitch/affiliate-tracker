<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Prove that mail actually works, before anyone depends on it.
 *
 * Sign-in requires an emailed code, so a broken mail transport does not degrade
 * this application, it stops it entirely. This command exists so that failure
 * is discovered deliberately on a Tuesday afternoon rather than by a creator at
 * the moment they try to check their earnings.
 */
class VerifyMailSetup extends Command
{
    protected $signature = 'mail:verify {email? : Where to send the test, defaults to the admin address}';

    protected $description = 'Send a test email and report exactly what is wrong if it fails';

    public function handle(): int
    {
        $to = $this->argument('email') ?: (string) config('affiliate.admin.email');
        $mailer = (string) config('mail.default');

        $this->newLine();
        $this->line('Configuration');
        $this->line('  Mailer        '.$mailer);
        $this->line('  Host          '.(config('mail.mailers.smtp.host') ?: '—'));
        $this->line('  Port          '.(config('mail.mailers.smtp.port') ?: '—'));
        $this->line('  Username      '.(config('mail.mailers.smtp.username') ?: '— not set'));
        $this->line('  Password      '.(config('mail.mailers.smtp.password') ? 'set' : '— not set'));
        $this->line('  From          '.config('mail.from.address').' ('.config('mail.from.name').')');
        $this->line('  Sending to    '.$to);
        $this->newLine();

        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error("“{$to}” is not a valid email address.");

            return self::FAILURE;
        }

        if ($mailer === 'log') {
            $this->warn('MAIL_MAILER is "log". Nothing will actually be sent — the message is written');
            $this->warn('to storage/logs/laravel.log. Set MAIL_MAILER=smtp with real credentials to');
            $this->warn('deliver mail for real.');
            $this->newLine();
        }

        if ($mailer === 'smtp' && ! config('mail.mailers.smtp.username')) {
            $this->warn('SMTP is selected but MAIL_USERNAME is empty. Most providers will reject this.');
            $this->newLine();
        }

        try {
            Mail::raw(
                "This is a test from the Pitch Innovations affiliate dashboard.\n\n".
                "If you are reading this, sign-in codes and settlement emails will reach you.\n\n".
                'Sent via the "'.$mailer."\" mailer at ".now()->toDayDateTimeString().'.',
                function ($message) use ($to) {
                    $message->to($to)->subject('Affiliate dashboard — mail test');
                }
            );
        } catch (Throwable $e) {
            $this->newLine();
            $this->error('Sending failed.');
            $this->line('  '.$e->getMessage());
            $this->newLine();
            $this->line('Common causes:');
            $this->line('  · Wrong host or port — 587 for TLS, 465 for SSL.');
            $this->line('  · Google Workspace needs an App Password, not the account password.');
            $this->line('  · The From address must be one the provider lets you send as.');
            $this->line('  · The host firewall may block outbound SMTP; try from the server itself.');
            $this->newLine();

            return self::FAILURE;
        }

        $this->info('Sent without error.');

        if ($mailer === 'log') {
            $this->line('Check storage/logs/laravel.log — that is where it went.');
        } else {
            $this->line("Check {$to}, including the spam folder. If it does not arrive, the transport");
            $this->line('accepted it but the provider dropped it — usually an unverified From address.');
        }

        return self::SUCCESS;
    }
}
