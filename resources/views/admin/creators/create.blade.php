@extends('layouts.app')

@section('title', 'Add a creator')

@section('content')

    <div class="page-head">
        <div>
            <h1>Add a creator</h1>
            <p>Sets up their account, their coupon codes and their dashboard in one go.</p>
        </div>
        <div class="page-head__actions">
            <a class="btn btn--outline" href="{{ route('admin.creators.index') }}">Cancel</a>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.creators.store') }}">
        @csrf

        <div class="stack">

            <section class="card">
                <div class="card__head"><h2>Who they are</h2></div>
                <div class="card__body">
                    <div class="form-grid">
                        <div class="field">
                            <label for="name">Full name</label>
                            <input id="name" name="name" type="text" class="input" required autofocus
                                   value="{{ old('name') }}" maxlength="160">
                        </div>

                        <div class="field">
                            <label for="email">Email</label>
                            <input id="email" name="email" type="email" class="input" required
                                   value="{{ old('email') }}">
                            <p class="hint">
                                Must be the email on their Pitch Innovations account — it identifies
                                them at sign-in and is where their one-time codes go.
                            </p>
                        </div>

                        <div class="field">
                            <label for="date_of_birth">Date of birth</label>
                            <input id="date_of_birth" name="date_of_birth" type="date" class="input" required
                                   value="{{ old('date_of_birth') }}" max="{{ now()->subYear()->toDateString() }}">
                            <p class="hint">Used as the third sign-in step. Get this right or they cannot log in.</p>
                        </div>

                        <div class="field">
                            <label for="country_code">Country code</label>
                            <input id="country_code" name="country_code" type="text" class="input"
                                   value="{{ old('country_code', 'IN') }}" maxlength="2"
                                   style="text-transform: uppercase;">
                            <p class="hint">Two letters, e.g. IN or US.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="card__head"><h2>Commission &amp; codes</h2></div>
                <div class="card__body">
                    <div class="form-grid">
                        <div class="field">
                            <label for="commission_rate">Commission rate</label>
                            <span class="input-suffix" data-suffix="%">
                                <input id="commission_rate" name="commission_rate" type="number" class="input"
                                       required step="0.01" min="0" max="100"
                                       value="{{ old('commission_rate', '15') }}">
                            </span>
                            <p class="hint">
                                Applied to the sale value excluding GST, as agreed with them.
                            </p>
                        </div>

                        <div class="field">
                            <label for="payout_currency">Paid in</label>
                            <select id="payout_currency" name="payout_currency" class="input" required>
                                @foreach (config('affiliate.payout_currencies') as $currency)
                                    <option value="{{ $currency }}" @selected(old('payout_currency', 'INR') === $currency)>
                                        {{ $currency }}{{ $currency === 'INR' ? ' — creators based in India' : ' — creators based abroad' }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="hint">
                                Their summary totals are converted to this currency. Order rows always
                                show what the customer actually paid.
                            </p>
                        </div>

                        <div class="field field--wide">
                            <label for="codes">Coupon codes</label>
                            <input id="codes" name="codes" type="text" class="input" required
                                   value="{{ old('codes') }}" placeholder="AARAV15, AARAVSUMMER">
                            <p class="hint">
                                Separate several with commas. All of a creator’s codes roll up into one
                                set of totals, and the orders table shows which code was used.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="card__head">
                    <h2>Payout details <span class="muted small" style="font-weight: 400;">— optional, they can fill these in themselves</span></h2>
                </div>
                <div class="card__body">
                    <div class="form-grid">
                        <div class="field">
                            <label for="payout_account_name">Account name</label>
                            <input id="payout_account_name" name="payout_account_name" type="text" class="input"
                                   value="{{ old('payout_account_name') }}" maxlength="160">
                        </div>

                        <div class="field">
                            <label for="sale_notification_frequency">New-sale emails</label>
                            <select id="sale_notification_frequency" name="sale_notification_frequency" class="input" required>
                                <option value="immediate" @selected(old('sale_notification_frequency', 'immediate') === 'immediate')>Straight away — one per sale</option>
                                <option value="hourly" @selected(old('sale_notification_frequency') === 'hourly')>Hourly digest</option>
                                <option value="daily" @selected(old('sale_notification_frequency') === 'daily')>Daily digest</option>
                            </select>
                            <p class="hint">They can change this themselves later.</p>
                        </div>

                        <div class="field">
                            <label for="gst_number">GST number</label>
                            <input id="gst_number" name="gst_number" type="text" class="input"
                                   value="{{ old('gst_number') }}" maxlength="20">
                        </div>

                        <div class="field">
                            <label for="pan_number">PAN</label>
                            <input id="pan_number" name="pan_number" type="text" class="input"
                                   value="{{ old('pan_number') }}" maxlength="20">
                        </div>

                        <div class="field field--wide">
                            <label for="payout_details">Bank / payment details</label>
                            <textarea id="payout_details" name="payout_details" class="input" rows="3"
                                      maxlength="2000">{{ old('payout_details') }}</textarea>
                        </div>
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="card__head"><h2>Password &amp; invite</h2></div>
                <div class="card__body">
                    <div class="alert alert--info mb-0" style="margin-bottom: 16px;">
                        A strong password is generated automatically when you save. It is shown
                        <strong>once</strong>, on the next screen. Passwords are stored hashed and cannot be
                        looked up afterwards — if it is lost, issue a new one.
                    </div>

                    <div class="switch-row switch-row--master" style="margin-bottom: 0;">
                        <input type="checkbox" id="send_welcome" name="send_welcome" value="1"
                               @checked(old('send_welcome', true))>
                        <div>
                            <label class="switch-row__label" for="send_welcome">
                                Email them their login details now
                            </label>
                            <div class="switch-row__hint">
                                Sends their email, password, coupon codes and step-by-step sign-in
                                instructions. You can also send this later from their page.
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="row row--end">
                <a class="btn btn--outline" href="{{ route('admin.creators.index') }}">Cancel</a>
                <button type="submit" class="btn btn--primary">Create dashboard</button>
            </div>

        </div>
    </form>

@endsection
