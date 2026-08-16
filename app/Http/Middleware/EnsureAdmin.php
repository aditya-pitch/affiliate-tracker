<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Spec section 5.7: recording a payment "is an internal / admin function;
 * please restrict it to authorised team members."
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        abort_unless($user->isAdmin() && $user->is_active, 403);

        return $next($request);
    }
}
