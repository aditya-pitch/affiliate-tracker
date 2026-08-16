<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/**
 * Settings, reachable from the header (spec sections 4 and 6).
 *
 * Note what a creator cannot change here: their email address (it is the
 * identity tied to their Pitch Innovations account), their date of birth (it is
 * a sign-in factor), and their commission rate (ours to set). Those are our
 * side of the arrangement, so they are shown read-only with a line telling the
 * creator to get in touch.
 */
class SettingsController extends Controller
{
    public function edit(): View
    {
        $user = $this->user();

        return view('settings.edit', [
            'user' => $user,
            'profile' => $user->profile,
            'couponCodes' => $user->couponCodes,
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $this->user();

        $validated = $request->validate([
            'display_name' => ['nullable', 'string', 'max:120'],
            'payout_account_name' => ['nullable', 'string', 'max:160'],
            'payout_details' => ['nullable', 'string', 'max:2000'],
            'gst_number' => ['nullable', 'string', 'max:20'],
            'pan_number' => ['nullable', 'string', 'max:20'],
        ]);

        $user->profile->update($validated);

        return back()->with('status', 'Your details have been saved.');
    }

    /**
     * Spec section 6.1: a master switch turns activity emails all on or off,
     * with an individual switch for each. Settlement emails are not listed
     * here on purpose -- section 6.2 says those are always sent.
     */
    public function updateNotifications(Request $request): RedirectResponse
    {
        $user = $this->user();

        $validated = $request->validate([
            'notify_master' => ['nullable', 'boolean'],
            'notify_on_sale' => ['nullable', 'boolean'],
            'notify_weekly_summary' => ['nullable', 'boolean'],
            'sale_notification_frequency' => ['required', 'in:immediate,hourly,daily'],
        ]);

        $user->profile->update([
            'notify_master' => $request->boolean('notify_master'),
            'notify_on_sale' => $request->boolean('notify_on_sale'),
            'notify_weekly_summary' => $request->boolean('notify_weekly_summary'),
            'sale_notification_frequency' => $validated['sale_notification_frequency'],
        ]);

        return back()->with('status', 'Your notification preferences have been updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $this->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers()],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'That is not your current password.']);
        }

        $user->forceFill(['password' => $validated['password']])->save();

        Log::channel('audit')->info('Password changed', ['user_id' => $user->id]);

        return back()->with('status', 'Your password has been changed.');
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        $user->loadMissing('profile', 'couponCodes');

        return $user;
    }
}
