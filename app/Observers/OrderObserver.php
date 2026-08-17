<?php

namespace App\Observers;

use App\Mail\OrderPlacedMail;
use App\Models\NotificationLogEntry;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Spec section 6.1: "Coupon code sales -- email me every time someone buys with
 * my code."
 *
 * Only creators on the "immediate" setting are emailed from here. Hourly and
 * daily creators are picked up by the SendOrderDigests command instead.
 *
 * This fires on Eloquent create, so whatever eventually feeds orders in from
 * the checkout (spec section 7, deferred) will get this behaviour for free --
 * as long as it creates orders through the model rather than bulk-inserting.
 */
class OrderObserver
{
    public function created(Order $order): void
    {
        $order->loadMissing('user.profile', 'couponCode', 'sale');

        $user = $order->user;
        $profile = $user?->profile;

        if (! $user || ! $profile || ! $user->is_active) {
            return;
        }

        // An order that arrives already marked refunded (a back-dated import,
        // say) is not something to congratulate anyone about.
        if ($order->is_refunded) {
            return;
        }

        if (! $profile->wantsActivityEmail('sale')) {
            return;
        }

        if ($profile->sale_notification_frequency !== 'immediate') {
            return;
        }

        /*
         | This fires on order creation, so when the checkout integration is
         | wired up (spec section 7) it will run inside whatever writes orders
         | in. A mail outage must never fail an order — the sale happened, the
         | commission is owed, and the dashboard will show it regardless of
         | whether the courtesy email got out.
         */
        try {
            Mail::to($user->email)->send(new OrderPlacedMail($order));

            NotificationLogEntry::create([
                'user_id' => $user->id,
                'sale_id' => $order->sale_id,
                'order_id' => $order->id,
                'type' => NotificationLogEntry::TYPE_ORDER,
                'sent_at' => Carbon::now(),
            ]);
        } catch (Throwable $e) {
            Log::channel('audit')->error('New-order email failed', [
                'user_id' => $user->id,
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
