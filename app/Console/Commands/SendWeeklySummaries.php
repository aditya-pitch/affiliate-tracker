<?php

namespace App\Console\Commands;

use App\Mail\WeeklySummaryMail;
use App\Models\NotificationLogEntry;
use App\Models\Order;
use App\Models\User;
use App\Services\CommissionCalculator;
use App\Services\EncouragementService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Spec section 6.1: "Weekly summary -- a Sunday recap of how my code did."
 */
class SendWeeklySummaries extends Command
{
    protected $signature = 'affiliates:send-weekly-summaries';

    protected $description = 'Send each opted-in creator a recap of how their coupon codes did this week';

    public function handle(
        CommissionCalculator $calculator,
        EncouragementService $encouragement,
    ): int {
        $weekEnd = Carbon::now();
        $weekStart = $weekEnd->copy()->subWeek();
        $weekLabel = $weekStart->format('j M').' – '.$weekEnd->format('j M Y');

        $users = User::query()
            ->with('profile')
            ->where('role', User::ROLE_AFFILIATE)
            ->where('is_active', true)
            ->whereHas('profile', function ($query) {
                $query->where('notify_master', true)
                    ->where('notify_weekly_summary', true);
            })
            ->get();

        $sent = 0;

        foreach ($users as $user) {
            $base = Order::query()
                ->where('user_id', $user->id)
                ->whereBetween('placed_at', [$weekStart, $weekEnd]);

            $unitsSold = (clone $base)->where('is_refunded', false)->count();

            // A creator with a quiet week is not emailed a page of zeroes.
            // Nothing in this dashboard should ever read as a telling-off.
            if ($unitsSold === 0) {
                continue;
            }

            $summary = $calculator->summarise(
                currency: $user->payoutCurrency(),
                unitsSold: $unitsSold,
                refundedOrders: (clone $base)->where('is_refunded', true)->count(),
                grossEarnings: (float) (clone $base)->where('is_refunded', false)->sum('converted_amount'),
                commissionRate: $user->commissionRate(),
            );

            Mail::to($user->email)->send(new WeeklySummaryMail(
                user: $user,
                summary: $summary,
                weekLabel: $weekLabel,
                encouragement: $encouragement->milestoneFor(
                    $user,
                    $unitsSold,
                    $summary->payoutAmount,
                    $summary->currency,
                ),
            ));

            NotificationLogEntry::create([
                'user_id' => $user->id,
                'type' => NotificationLogEntry::TYPE_WEEKLY_SUMMARY,
                'sent_at' => Carbon::now(),
            ]);

            $sent++;
        }

        $this->info("{$sent} weekly summaries sent.");

        return self::SUCCESS;
    }
}
