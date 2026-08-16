<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Spec section 3: "Because financial data is shown, sessions should time out
 * after a period of inactivity (maybe 20 mins)".
 *
 * The session driver's own lifetime is a blunter instrument -- it is refreshed
 * by any request at all, including the dashboard's own five-second polling.
 * Left to it, a creator who walked away from an open dashboard would never be
 * signed out, because the page keeps their session alive on their behalf.
 *
 * So idleness is tracked explicitly here, and the polling endpoint is excluded
 * from refreshing it.
 */
class EnforceSessionTimeout
{
    private const KEY = 'last_activity_at';

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $timeout = (int) config('affiliate.idle_timeout_minutes', 20);
        $lastActivity = $request->session()->get(self::KEY);

        if ($lastActivity !== null) {
            $expiresAt = Carbon::createFromTimestamp($lastActivity)->addMinutes($timeout);

            if ($expiresAt->isPast()) {
                return $this->timeOut($request);
            }
        }

        /*
         | Background polling keeps the numbers moving but does not count as the
         | creator being at their desk. Anything they actually clicked does.
         */
        if (! $this->isBackgroundRequest($request)) {
            $request->session()->put(self::KEY, Carbon::now()->timestamp);
        }

        return $next($request);
    }

    private function timeOut(Request $request): Response
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $message = 'You were signed out after 20 minutes of inactivity.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'redirect' => route('login')], 401);
        }

        return redirect()->route('login')->with('status', $message);
    }

    private function isBackgroundRequest(Request $request): bool
    {
        return $request->routeIs('dashboard.live');
    }
}
