@extends('layouts.app')

@section('title', 'Settings')

@section('content')

    <h1 style="margin-bottom: 20px;">Settings</h1>

    <div class="stack">

        {{-- --- Account ------------------------------------------------- --}}
        <section class="card">
            <div class="card__head"><h2>Your account</h2></div>
            <div class="card__body">

                <div class="field">
                    <label>Email</label>
                    <input class="input" type="email" value="{{ $user->email }}" disabled>
                    <p class="hint">
                        This is the email on your Pitch Innovations account, and where your
                        sign-in codes go. To change it, email
                        <a href="mailto:support@pitchinnovations.com">support@pitchinnovations.com</a>.
                    </p>
                </div>

                <div class="row" style="gap: 20px; align-items: flex-start;">
                    <div class="grow field" style="min-width: 200px;">
                        <label>Your commission rate</label>
                        <input class="input" type="text" value="{{ $profile->commissionRatePercent() }}" disabled>
                        <p class="hint">Set by us, and applied to every order on your codes.</p>
                    </div>

                    <div class="grow field" style="min-width: 200px;">
                        <label>Paid in</label>
                        <input class="input" type="text" value="{{ $profile->payout_currency }}" disabled>
                        <p class="hint">Your totals are converted to this currency.</p>
                    </div>
                </div>

                <div class="field">
                    <label>Your coupon {{ $couponCodes->count() === 1 ? 'code' : 'codes' }}</label>
                    <p>
                        @forelse ($couponCodes as $code)
                            <span class="code" style="font-size: 14px; padding: 5px 11px; margin-right: 6px;">{{ $code->code }}</span>
                        @empty
                            <span class="muted small">No codes assigned yet.</span>
                        @endforelse
                    </p>
                    @if ($couponCodes->count() > 1)
                        <p class="hint">All of your codes roll up into the totals on your dashboard.</p>
                    @endif
                </div>

                <form method="POST" action="{{ route('settings.profile') }}" style="margin-top: 8px;">
                    @csrf
                    @method('PUT')

                    <div class="field">
                        <label for="display_name">Display name</label>
                        <input id="display_name" name="display_name" type="text" class="input"
                               value="{{ old('display_name', $profile->display_name) }}" maxlength="120">
                        <p class="hint">What we call you around the dashboard and in emails.</p>
                    </div>

                    <h3 style="margin: 22px 0 12px;">Payout details</h3>

                    <div class="field">
                        <label for="payout_account_name">Account name</label>
                        <input id="payout_account_name" name="payout_account_name" type="text" class="input"
                               value="{{ old('payout_account_name', $profile->payout_account_name) }}" maxlength="160">
                    </div>

                    <div class="field">
                        <label for="payout_details">Bank / payment details</label>
                        <textarea id="payout_details" name="payout_details" class="input" rows="4"
                                  maxlength="2000">{{ old('payout_details', $profile->payout_details) }}</textarea>
                        <p class="hint">Account number, IFSC or SWIFT, and anything else we need to pay you.</p>
                    </div>

                    <div class="row" style="gap: 20px; align-items: flex-start;">
                        <div class="grow field" style="min-width: 180px;">
                            <label for="gst_number">GST number <span class="muted">(optional)</span></label>
                            <input id="gst_number" name="gst_number" type="text" class="input"
                                   value="{{ old('gst_number', $profile->gst_number) }}" maxlength="20">
                        </div>
                        <div class="grow field" style="min-width: 180px;">
                            <label for="pan_number">PAN <span class="muted">(optional)</span></label>
                            <input id="pan_number" name="pan_number" type="text" class="input"
                                   value="{{ old('pan_number', $profile->pan_number) }}" maxlength="20">
                        </div>
                    </div>

                    <button type="submit" class="btn btn--dark">Save details</button>
                </form>

            </div>
        </section>

        {{-- --- Notifications (spec section 6) --------------------------- --}}
        <section class="card">
            <div class="card__head"><h2>Email notifications</h2></div>
            <div class="card__body">

                <form method="POST" action="{{ route('settings.notifications') }}">
                    @csrf
                    @method('PUT')

                    <div class="switch-row switch-row--master">
                        <input type="checkbox" id="notify_master" name="notify_master" value="1"
                               @checked(old('notify_master', $profile->notify_master))>
                        <div>
                            <label class="switch-row__label" for="notify_master">All activity emails</label>
                            <div class="switch-row__hint">
                                Turn this off and we will not send you any of the emails below.
                            </div>
                        </div>
                    </div>

                    <div class="switch-row">
                        <input type="checkbox" id="notify_on_sale" name="notify_on_sale" value="1"
                               @checked(old('notify_on_sale', $profile->notify_on_sale))>
                        <div>
                            <label class="switch-row__label" for="notify_on_sale">Coupon code sales</label>
                            <div class="switch-row__hint">Tell me when someone buys with my code.</div>
                        </div>
                    </div>

                    <div class="field" style="margin: 4px 0 16px 29px; max-width: 320px;">
                        <label for="sale_notification_frequency">How often</label>
                        <select id="sale_notification_frequency" name="sale_notification_frequency" class="input">
                            <option value="immediate" @selected(old('sale_notification_frequency', $profile->sale_notification_frequency) === 'immediate')>
                                Straight away — one email per sale
                            </option>
                            <option value="hourly" @selected(old('sale_notification_frequency', $profile->sale_notification_frequency) === 'hourly')>
                                Hourly — one email with everything from that hour
                            </option>
                            <option value="daily" @selected(old('sale_notification_frequency', $profile->sale_notification_frequency) === 'daily')>
                                Daily — one email a day
                            </option>
                        </select>
                        <p class="hint">
                            During a big sale, hourly or daily keeps your inbox manageable.
                        </p>
                    </div>

                    <div class="switch-row">
                        <input type="checkbox" id="notify_weekly_summary" name="notify_weekly_summary" value="1"
                               @checked(old('notify_weekly_summary', $profile->notify_weekly_summary))>
                        <div>
                            <label class="switch-row__label" for="notify_weekly_summary">Weekly summary</label>
                            <div class="switch-row__hint">A Sunday recap of how your code did.</div>
                        </div>
                    </div>

                    <div class="alert alert--info" style="margin: 20px 0;">
                        Emails about getting paid — a sale ending and your report being ready, and
                        confirmation once we have paid you — are always sent, whatever you choose above.
                    </div>

                    <button type="submit" class="btn btn--dark">Save preferences</button>
                </form>

            </div>
        </section>

        {{-- --- Password ------------------------------------------------ --}}
        <section class="card">
            <div class="card__head"><h2>Change your password</h2></div>
            <div class="card__body">

                <form method="POST" action="{{ route('settings.password') }}" style="max-width: 420px;">
                    @csrf
                    @method('PUT')

                    <div class="field">
                        <label for="current_password">Current password</label>
                        <input id="current_password" name="current_password" type="password" class="input"
                               required autocomplete="current-password">
                    </div>

                    <div class="field">
                        <label for="password">New password</label>
                        <input id="password" name="password" type="password" class="input"
                               required autocomplete="new-password">
                        <p class="hint">At least 10 characters, with letters and numbers.</p>
                    </div>

                    <div class="field">
                        <label for="password_confirmation">Confirm new password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" class="input"
                               required autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn--dark">Change password</button>
                </form>

            </div>
        </section>

    </div>

    <p class="small muted" style="margin-top: 20px;">
        <a href="{{ route('dashboard') }}">← Back to your dashboard</a>
    </p>

@endsection
