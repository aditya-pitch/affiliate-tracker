<?php

use App\Console\Commands\CloseEndedSales;
use App\Console\Commands\SendOrderDigests;
use App\Console\Commands\SendWeeklySummaries;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled work
|--------------------------------------------------------------------------
|
| All of this needs a single cron entry on the server:
|
|     * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
|
*/

/*
| Spec section 5.7: when a sale closes, its reports are finalised and the
| creators are emailed. Checked every minute so a sale that ends at 23:59 is
| settled at 23:59, not the next morning.
*/
Schedule::command(CloseEndedSales::class)
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

/*
| Spec section 6.1, batched digest option. Runs hourly; the command itself
| works out which creators are due an hourly digest and which a daily one.
*/
Schedule::command(SendOrderDigests::class)
    ->hourly()
    ->withoutOverlapping();

/*
| Spec section 6.1: "Weekly summary -- a Sunday recap of how my code did."
*/
Schedule::command(SendWeeklySummaries::class)
    ->weeklyOn(0, '09:00')
    ->withoutOverlapping();
