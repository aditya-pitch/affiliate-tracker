<?php

namespace App\Providers;

use App\Models\Order;
use App\Observers\OrderObserver;
use App\Services\CommissionCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /*
         | CommissionCalculator takes the GST and transaction-fee rates as
         | constructor arguments, which the container cannot work out on its
         | own. Bound here so every service that depends on it -- the summary,
         | the settlement snapshot, the per-order email -- is guaranteed to be
         | using the same rates from config/affiliate.php.
         */
        $this->app->singleton(
            CommissionCalculator::class,
            fn () => CommissionCalculator::fromConfig()
        );
    }

    public function boot(): void
    {
        // The orders table is the hot path on a live sale, so an accidental
        // N+1 should fail loudly in development rather than quietly turn one
        // page load into a few hundred queries.
        Model::preventLazyLoading(! app()->isProduction());

        // Sends the per-order email to creators who have asked for one
        // (spec section 6.1).
        Order::observe(OrderObserver::class);

        if (app()->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
