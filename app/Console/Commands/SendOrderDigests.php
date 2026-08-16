<?php

namespace App\Console\Commands;

use App\Mail\OrderDigestMail;
use App\Models\NotificationLogEntry;
use App\Models\Order;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * The batched alternative to one email per order (spec section 6.1, confirmed
 * during spec review).
 *
 * Runs hourly. Creators on "immediate" are not touched here -- they are emailed
 * by OrderObserver as each order lands. Creators on "daily" are only sent one
 * once a day has passed since their last digest.
 */
class SendOrderDigests extends Command
{
    protected $signature = 'affiliates:send-digests';

    protected $description = 'Send batched new-order digests to creators who have chosen hourly or daily emails';

    public function handle(): int
    {
        $users = User::query()
            ->with('profile')
            ->where('role', User::ROLE_AFFILIATE)
            ->where('is_active', true)
            ->whereHas('profile', function ($query) {
                $query->where('notify_master', true)
                    ->where('notify_on_sale', true)
                    ->whereIn('sale_notification_frequency', ['hourly', 'daily']);
            })
            ->get();

        $sent = 0;

        foreach ($users as $user) {
            $profile = $user->profile;

            $since = $this->windowStart($user);

            if ($since === null) {
                continue;
            }

            $orders = Order::query()
                ->with('couponCode')
                ->where('user_id', $user->id)
                ->where('is_refunded', false)
                ->where('placed_at', '>', $since)
                ->orderBy('placed_at')
                ->get();

            // Nothing to report is not a reason to email someone. A quiet
            // window simply produces no digest.
            if ($orders->isEmpty()) {
                continue;
            }

            Mail::to($user->email)->send(new OrderDigestMail(
                user: $user,
                orders: $orders,
                period: $profile->sale_notification_frequency === 'daily' ? 'today' : 'in the last hour',
            ));

            $profile->forceFill(['last_digest_sent_at' => Carbon::now()])->save();

            NotificationLogEntry::create([
                'user_id' => $user->id,
                'type' => NotificationLogEntry::TYPE_ORDER_DIGEST,
                'sent_at' => Carbon::now(),
            ]);

            $sent++;
        }

        $this->info("{$sent} digests sent.");

        return self::SUCCESS;
    }

    /**
     * Where this creator's digest window starts, or null if they are not due
     * one yet.
     *
     * Anchored on when the last digest actually went out rather than on the
     * clock, so an hour where the scheduler did not run does not silently drop
     * those orders out of the next email.
     */
    private function windowStart(User $user): ?Carbon
    {
        $profile = $user->profile;
        $last = $profile->last_digest_sent_at;

        $interval = $profile->sale_notification_frequency === 'daily' ? 24 : 1;

        if ($last === null) {
            return Carbon::now()->subHours($interval);
        }

        // Not due yet. A minute of slack absorbs the scheduler firing a few
        // seconds early relative to the previous run.
        if ($last->copy()->addHours($interval)->subMinute()->isFuture()) {
            return null;
        }

        return $last;
    }
}
