<?php

namespace App\Console\Commands;

use App\Exceptions\CouldNotSendLoginCode;
use App\Models\LoginCode;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Break glass: issue a sign-in code and print it to the console instead of
 * emailing it.
 *
 * The point of this command is that mail is a single point of failure for
 * getting into this application at all. If SMTP breaks, or the admin address is
 * changed to one nobody can read yet, or a provider starts silently binning our
 * mail, then without this there is no way back in and payout information is
 * unreachable until the mail problem is solved.
 *
 * It is not a security hole: running it requires shell access to the server,
 * which is strictly more access than any account it could let you into. Its use
 * is written to the audit log.
 */
class IssueLoginCode extends Command
{
    protected $signature = 'affiliate:login-code
                            {email : The account to issue a code for}
                            {--send : Email it as well as printing it}';

    protected $description = 'Break glass — issue a sign-in code and print it, for when email is not working';

    public function handle(OtpService $otp): int
    {
        $email = strtolower(trim($this->argument('email')));

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("No account found for {$email}");

            return self::FAILURE;
        }

        if (! $user->is_active) {
            $this->error("{$email} is not an active account.");

            return self::FAILURE;
        }

        /*
         | If --send is used and the mail transport is down, the code is still
         | printed. Failing to deliver is precisely the situation this command
         | exists for, so it must not swallow the code on the way out.
         */
        try {
            $record = $otp->issue($user, Request::createFromGlobals(), deliver: (bool) $this->option('send'));
        } catch (CouldNotSendLoginCode $e) {
            $this->warn('Could not email the code ('.$e->getPrevious()?->getMessage().'), but it was issued.');

            $record = LoginCode::where('user_id', $user->id)->latest('id')->firstOrFail();
            $this->error('The code itself could not be recovered — it is only held hashed.');
            $this->line('Run this command again without --send to issue one that is printed here.');

            return self::FAILURE;
        }

        Log::channel('audit')->warning('Sign-in code issued from the console', [
            'user_id' => $user->id,
            'email' => $user->email,
            'emailed' => (bool) $this->option('send'),
        ]);

        $this->newLine();
        $this->info("Sign-in code for {$user->email}");
        $this->newLine();
        $this->warn('    '.$record->plainCode);
        $this->newLine();
        $this->line('  Valid for '.config('affiliate.otp.ttl_minutes').' minutes, single use.');
        $this->line('  Any earlier code for this account has been invalidated.');
        $this->newLine();
        $this->line('  Complete the first three sign-in steps in the browser first —');
        $this->line('  email, password, date of birth — then enter this code.');
        $this->newLine();

        return self::SUCCESS;
    }
}
