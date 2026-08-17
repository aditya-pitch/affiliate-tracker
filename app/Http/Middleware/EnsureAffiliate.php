<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Spec section 9: creators can only access their own dashboard and data.
 *
 * This is the outer gate -- it establishes that the signed-in user is an active
 * affiliate at all. Which rows they may see is enforced separately, at every
 * query, by scoping to the authenticated user.
 */
class EnsureAffiliate
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'This account is not active. Please get in touch with us.']);
        }

        // An admin is a member of our team, not a creator: they have no coupon
        // codes and no dashboard of their own to look at.
        if (! $user->isAffiliate()) {
            return redirect()->route('admin.overview.index');
        }

        return $next($request);
    }
}
