<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * The nudges that appear over the dashboard while a sale runs (spec section 1).
 *
 * The brief on tone is firm and worth repeating here, because it is the kind of
 * thing a later change can quietly undo:
 *
 *   "Never display messages like 'Sorry, your code is not doing well'. Never
 *    demotivate a creator. Always tell them that the efforts they have put in
 *    to make the content is bringing colours. Push them to do better."
 *
 * So there is no "underperforming" bucket, and no bucket is phrased as a
 * comparison against other creators or against a target. A quiet hour is framed
 * as an opportunity to post, never as a failure. If you add messages here,
 * keep that rule: every line should read well to a creator having a slow day.
 */
final class EncouragementService
{
    /**
     * Messages a creator sees before their first order of the sale lands.
     * These carry the most weight -- this is the moment a creator is most
     * likely to feel discouraged, so nothing here implies anything is wrong.
     *
     * @var list<string>
     */
    private const STARTING = [
        'Your code is live. Every story you post from here goes straight to your numbers.',
        'You are on the board and ready to go. Share your link in your stories and watch this page move.',
        'The sale has opened with your code active. The content you have made is out there working for you.',
        'All set. Post your demo clip today and give your audience the nudge they are waiting for.',
    ];

    /**
     * The first handful of orders.
     *
     * @var list<string>
     */
    private const EARLY = [
        'Your first sales are in. The content you put out is bringing colours already.',
        'Your code is converting. Keep the momentum going with a story today.',
        'People are buying with your code. A quick reel now could double this.',
        'This is working. Pin your link to your profile so nobody has to hunt for it.',
    ];

    /**
     * A sale that is building steadily.
     *
     * @var list<string>
     */
    private const BUILDING = [
        'Your code is doing really well. Keep pushing the plugin on stories for more reach.',
        'Strong run so far. Your audience clearly trusts your word on this one.',
        'The numbers are climbing. A walkthrough post right now would land beautifully.',
        'Great work. Try posting at your peak hour tonight and give this another lift.',
    ];

    /**
     * A standout sale.
     *
     * @var list<string>
     */
    private const STRONG = [
        'Outstanding. Your code is one of the most active on this sale.',
        'This is a brilliant run. Your effort on the content is paying off properly.',
        'Excellent numbers. Share a behind-the-scenes clip and keep this rolling.',
        'You are on a roll. Your audience is responding to exactly what you are making.',
    ];

    /**
     * Several orders in the last hour -- worth calling out while it is hot.
     *
     * @var list<string>
     */
    private const MOMENTUM = [
        'Orders are coming in right now. This is the moment to post a story.',
        'Your code is moving fast this hour. Ride it -- put something up while your audience is online.',
        'Real momentum on your code right now. One more push could make this your best sale yet.',
    ];

    /**
     * Shown once a sale has closed.
     *
     * @var list<string>
     */
    private const ENDED = [
        'Sale wrapped. Thank you for everything you put into this one.',
        'That is a wrap on this sale. Your report below is final and ready to download.',
        'Great campaign. Upload your invoice below and we will get you paid.',
    ];

    /**
     * Pick the message to show for a creator on a given sale.
     *
     * @param  int  $unitsSold  Successful orders on this creator's codes.
     * @param  int  $recentOrders  Orders in the last hour.
     */
    public function messageFor(Sale $sale, int $unitsSold, int $recentOrders = 0): string
    {
        $pool = match (true) {
            $sale->hasEnded() => self::ENDED,
            $recentOrders >= 3 => self::MOMENTUM,
            $unitsSold === 0 => self::STARTING,
            $unitsSold < 5 => self::EARLY,
            $unitsSold < 20 => self::BUILDING,
            default => self::STRONG,
        };

        return $pool[$this->rotationIndex(count($pool))];
    }

    /**
     * A milestone line, shown alongside the nudge when a creator has just
     * crossed a round number. Returns null most of the time -- these land
     * harder when they are rare.
     */
    public function milestoneFor(User $user, int $unitsSold, float $payout, string $currency): ?string
    {
        $name = $user->firstName();

        return match (true) {
            $unitsSold >= 100 => "{$name}, that is 100 sales on your code. Genuinely brilliant.",
            $unitsSold >= 50 => "50 sales, {$name}. Your audience is showing up for you.",
            $unitsSold >= 25 => "25 sales on your code -- and ".Money::format($payout, $currency).' earned so far.',
            $unitsSold >= 10 => "You have crossed 10 sales, {$name}. Keep going.",
            default => null,
        };
    }

    /**
     * Rotate through a pool so a creator watching the page for an hour does not
     * stare at one sentence. Derived from the clock rather than random so the
     * message does not flicker between two polls a few seconds apart.
     */
    private function rotationIndex(int $poolSize): int
    {
        if ($poolSize <= 1) {
            return 0;
        }

        // Changes every 45 seconds.
        return (int) floor(Carbon::now()->timestamp / 45) % $poolSize;
    }
}
