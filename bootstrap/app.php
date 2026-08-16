<?php

use App\Http\Middleware\EnforceSessionTimeout;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureAffiliate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Financial data is on screen, so every web request re-checks how long
        // the session has been idle (spec section 3).
        $middleware->web(append: [
            EnforceSessionTimeout::class,
        ]);

        $middleware->alias([
            'affiliate' => EnsureAffiliate::class,
            'admin' => EnsureAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
