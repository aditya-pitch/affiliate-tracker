<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/**
 * Spec section 3: "We need a 'forgot password' / reset flow."
 *
 * Resetting the password does not bypass anything: the next sign-in still walks
 * the full four steps, including the emailed code.
 */
class PasswordResetController extends Controller
{
    public function showRequestForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $status = Password::sendResetLink($request->only('email'));

        Log::channel('audit')->info('Password reset requested', [
            'email' => $request->input('email'),
            'ip' => $request->ip(),
            'result' => $status,
        ]);

        /*
         | Always the same confirmation, whether or not the address has an
         | account -- same reasoning as the sign-in form: this page must not
         | become a way to check which of our creators uses which email.
         */
        return back()->with('status', 'If that email is on an affiliate account, a reset link is on its way.');
    }

    public function showResetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));

                Log::channel('audit')->info('Password reset completed', ['user_id' => $user->id]);
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', 'Your password has been changed. Please sign in.')
            : back()->withErrors(['email' => __($status)]);
    }
}
