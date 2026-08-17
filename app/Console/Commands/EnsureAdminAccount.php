<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\CreatorProvisioner;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Create or update the internal admin account.
 *
 * Safe to run repeatedly: it converges on the requested state rather than
 * assuming what is already there. This is how the admin address gets changed
 * on a live install without editing rows by hand.
 */
class EnsureAdminAccount extends Command
{
    protected $signature = 'affiliate:admin
                            {email? : Address for the admin account, defaults to config affiliate.admin.email}
                            {--name= : Display name}
                            {--dob= : Date of birth in YYYY-MM-DD, required when creating}
                            {--replaces= : An existing admin address to rename, rather than adding a second admin}
                            {--password : Issue a new password and print it}';

    protected $description = 'Create or update the internal admin account';

    public function handle(CreatorProvisioner $provisioner): int
    {
        $email = strtolower(trim(
            $this->argument('email') ?: (string) config('affiliate.admin.email')
        ));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("“{$email}” is not a valid email address.");

            return self::FAILURE;
        }

        $existing = User::where('email', $email)->first();

        if ($existing && ! $existing->isAdmin()) {
            $this->error("{$email} already belongs to a creator account. Refusing to convert it — ".
                'pick a different address, or remove that creator first.');

            return self::FAILURE;
        }

        $renaming = $this->resolveAccountToRename($email);

        if ($renaming) {
            $this->line("Renaming existing admin {$renaming->email} → {$email}");
            $renaming->forceFill(['email' => $email])->save();
            $admin = $renaming;
        } elseif ($existing) {
            $this->line("Admin {$email} already exists — updating.");
            $admin = $existing;
        } else {
            $dob = $this->option('dob');

            if (! $dob) {
                $this->error('No admin account exists yet, so --dob=YYYY-MM-DD is required to create one. '.
                    'It is the third sign-in step.');

                return self::FAILURE;
            }

            $password = $provisioner->generatePassword();

            $admin = User::create([
                'name' => $this->option('name') ?: (string) config('affiliate.admin.name'),
                'email' => $email,
                'password' => $password,
                'date_of_birth' => $dob,
                'role' => User::ROLE_ADMIN,
                'is_active' => true,
            ]);

            $admin->forceFill(['password_issued_at' => Carbon::now()])->save();

            $this->info("Created admin account {$email}");
            $this->newLine();
            $this->warn("  Password: {$password}");
            $this->line('  Copy it now — it is stored hashed and cannot be shown again.');
            $this->newLine();
        }

        // Apply any updates requested alongside.
        $changes = [];

        if ($this->option('name')) {
            $changes['name'] = $this->option('name');
        }

        if ($this->option('dob')) {
            $changes['date_of_birth'] = $this->option('dob');
        }

        $changes['role'] = User::ROLE_ADMIN;
        $changes['is_active'] = true;

        $admin->forceFill($changes)->save();

        if ($this->option('password') && ! isset($password)) {
            $issued = $provisioner->resetPassword($admin);
            $this->newLine();
            $this->warn("  New password: {$issued}");
            $this->line('  Copy it now — it is stored hashed and cannot be shown again.');
            $this->newLine();
        }

        Log::channel('audit')->info('Admin account ensured via console', [
            'user_id' => $admin->id,
            'email' => $admin->email,
        ]);

        $this->newLine();
        $this->info('Admin account ready:');
        $this->line('  Email          '.$admin->email);
        $this->line('  Name           '.$admin->name);
        $this->line('  Date of birth  '.($admin->date_of_birth?->toDateString() ?? '— not set, sign-in will fail'));
        $this->line('  Active         '.($admin->is_active ? 'yes' : 'no'));
        $this->newLine();

        if (config('mail.default') === 'log') {
            $this->warn('MAIL_MAILER is "log", so sign-in codes are written to storage/logs/laravel.log');
            $this->warn('rather than emailed. Run  php artisan mail:verify  once real SMTP is configured.');
        }

        return self::SUCCESS;
    }

    /**
     * Find the admin account this one is meant to replace, so changing the
     * address renames the existing account rather than leaving two admins
     * behind — one of which nobody can reach.
     */
    private function resolveAccountToRename(string $newEmail): ?User
    {
        if ($replaces = $this->option('replaces')) {
            return User::where('email', strtolower(trim($replaces)))
                ->where('role', User::ROLE_ADMIN)
                ->first();
        }

        // No explicit target: if there is exactly one admin and it is not
        // already the requested address, that is unambiguously the one meant.
        $admins = User::where('role', User::ROLE_ADMIN)->get();

        if ($admins->count() === 1 && $admins->first()->email !== $newEmail) {
            return $admins->first();
        }

        return null;
    }
}
