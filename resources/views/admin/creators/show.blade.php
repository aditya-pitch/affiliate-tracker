@extends('layouts.app')

@section('title', $creator->name)

@section('content')

    <div class="page-head">
        <div>
            <h1>{{ $creator->name }}</h1>
            <p>
                {{ $creator->email }}
                @unless ($creator->is_active)
                    · <span class="badge badge--refunded">Account disabled</span>
                @endunless
            </p>
        </div>
        <div class="page-head__actions">
            <a class="btn btn--outline" href="{{ route('admin.creators.index') }}">All creators</a>
            @if ($salesTakenPart->isNotEmpty())
                <a class="btn btn--dark"
                   href="{{ route('admin.creators.dashboard', [$creator, $salesTakenPart->first()]) }}">
                    Open their dashboard
                </a>
            @endif
        </div>
    </div>

    {{--
        The one moment a password is visible. Flashed to the session by the
        controller and gone on the next request — it is never stored anywhere
        readable, so this is genuinely the only chance to copy it.
    --}}
    @if (session('issued_password'))
        <div class="reveal">
            <h3>Password for {{ $creator->name }}</h3>
            <p>
                Copy this now. It is stored hashed, so neither you nor anyone else can
                look it up again — if it is lost, issue a new one.
            </p>
            <div class="reveal__value">
                <span data-password>{{ session('issued_password') }}</span>
                <button type="button" class="btn btn--outline btn--sm" data-copy-password>Copy</button>
            </div>
        </div>
    @endif

    <div class="stack">

        {{-- --- Credentials ------------------------------------------- --}}
        <section class="card">
            <div class="card__head">
                <div>
                    <h2>Sign-in details</h2>
                    <p class="small muted mb-0">What this creator needs in order to log in.</p>
                </div>
            </div>
            <div class="card__body">
                <div class="facts">
                    <div>
                        <div class="facts__key">1 · Email</div>
                        <div class="facts__val facts__val--mono">{{ $creator->email }}</div>
                    </div>
                    <div>
                        <div class="facts__key">2 · Password</div>
                        <div class="facts__val">
                            @if ($creator->password_issued_at)
                                <span class="muted">Issued {{ $creator->password_issued_at->diffForHumans() }}</span>
                            @else
                                <span class="muted">Set at account creation</span>
                            @endif
                        </div>
                    </div>
                    <div>
                        <div class="facts__key">3 · Date of birth</div>
                        <div class="facts__val facts__val--mono">
                            {{ $creator->date_of_birth?->format('d/m/Y') ?? '—' }}
                        </div>
                    </div>
                    <div>
                        <div class="facts__key">4 · One-time code</div>
                        <div class="facts__val"><span class="muted">Emailed at sign-in</span></div>
                    </div>
                </div>

                <div class="alert alert--info" style="margin: 22px 0 16px;">
                    Passwords are stored hashed and cannot be displayed — not here, not anywhere.
                    If this creator has lost theirs, issue a new one below and it will be shown once.
                </div>

                <div class="row">
                    <form method="POST" action="{{ route('admin.creators.password', $creator) }}">
                        @csrf
                        <input type="hidden" name="send_email" value="1">
                        <button type="submit" class="btn btn--primary">
                            Issue a new password &amp; email it
                        </button>
                    </form>

                    <form method="POST" action="{{ route('admin.creators.password', $creator) }}">
                        @csrf
                        <button type="submit" class="btn btn--outline">
                            Issue a new password, show it here only
                        </button>
                    </form>

                    <form method="POST" action="{{ route('admin.creators.welcome', $creator) }}">
                        @csrf
                        <button type="submit" class="btn btn--outline">
                            {{ $creator->welcome_sent_at ? 'Resend sign-in instructions' : 'Send sign-in instructions' }}
                        </button>
                    </form>
                </div>

                @if ($creator->welcome_sent_at)
                    <p class="small muted" style="margin-top: 12px;">
                        Last invited {{ $creator->welcome_sent_at->diffForHumans() }}.
                    </p>
                @endif
            </div>
        </section>

        {{-- --- Coupon codes ------------------------------------------ --}}
        <section class="card">
            <div class="card__head">
                <div>
                    <h2>Coupon codes</h2>
                    <p class="small muted mb-0">
                        All of their codes roll up into one set of totals.
                    </p>
                </div>
            </div>
            <div class="card__body">
                @if ($creator->couponCodes->isEmpty())
                    <p class="muted small">
                        No codes yet — this creator has nothing to track until one is added.
                    </p>
                @else
                    <div class="table-scroll" style="margin-bottom: 18px;">
                        <table class="data">
                            <thead>
                            <tr>
                                <th>Code</th>
                                <th>Status</th>
                                <th>Added</th>
                                <th class="shrink"></th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($creator->couponCodes as $code)
                                <tr>
                                    <td><span class="code">{{ $code->code }}</span></td>
                                    <td>
                                        <span class="badge badge--{{ $code->is_active ? 'paid' : 'ended' }}">
                                            {{ $code->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="muted">{{ $code->created_at->format('j M Y') }}</td>
                                    <td class="shrink">
                                        <form method="POST" action="{{ route('admin.codes.toggle', $code) }}">
                                            @csrf
                                            <button type="submit" class="btn btn--outline btn--sm">
                                                {{ $code->is_active ? 'Deactivate' : 'Reactivate' }}
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.creators.codes.add', $creator) }}" class="row">
                    @csrf
                    <div class="field grow mb-0" style="max-width: 300px; margin-bottom: 0;">
                        <label for="code" class="sr-only">New coupon code</label>
                        <input id="code" name="code" type="text" class="input" required
                               placeholder="Add another code" maxlength="64">
                    </div>
                    <button type="submit" class="btn btn--dark">Add code</button>
                </form>
            </div>
        </section>

        {{-- --- Commission & payout ----------------------------------- --}}
        <section class="card">
            <div class="card__head"><h2>Commission &amp; details</h2></div>
            <div class="card__body">
                <form method="POST" action="{{ route('admin.creators.update', $creator) }}">
                    @csrf
                    @method('PUT')

                    <div class="form-grid">
                        <div class="field">
                            <label for="u_name">Full name</label>
                            <input id="u_name" name="name" type="text" class="input" required
                                   value="{{ old('name', $creator->name) }}" maxlength="160">
                        </div>
                        <div class="field">
                            <label for="u_email">Email</label>
                            <input id="u_email" name="email" type="email" class="input" required
                                   value="{{ old('email', $creator->email) }}">
                        </div>
                        <div class="field">
                            <label for="u_dob">Date of birth</label>
                            <input id="u_dob" name="date_of_birth" type="date" class="input" required
                                   value="{{ old('date_of_birth', $creator->date_of_birth?->toDateString()) }}"
                                   max="{{ now()->subYear()->toDateString() }}">
                        </div>
                        <div class="field">
                            <label for="u_rate">Commission rate</label>
                            <span class="input-suffix" data-suffix="%">
                                <input id="u_rate" name="commission_rate" type="number" class="input" required
                                       step="0.01" min="0" max="100"
                                       value="{{ old('commission_rate', rtrim(rtrim(number_format((float) $creator->profile->commission_rate * 100, 2, '.', ''), '0'), '.')) }}">
                            </span>
                        </div>
                        <div class="field">
                            <label for="u_currency">Paid in</label>
                            <select id="u_currency" name="payout_currency" class="input" required>
                                @foreach (config('affiliate.payout_currencies') as $currency)
                                    <option value="{{ $currency }}"
                                        @selected(old('payout_currency', $creator->profile->payout_currency) === $currency)>
                                        {{ $currency }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="hint">
                                Changing this affects future orders only — past orders keep the rate
                                locked when they were placed.
                            </p>
                        </div>
                        <div class="field">
                            <label for="u_country">Country code</label>
                            <input id="u_country" name="country_code" type="text" class="input" maxlength="2"
                                   value="{{ old('country_code', $creator->profile->country_code) }}"
                                   style="text-transform: uppercase;">
                        </div>
                        <div class="field">
                            <label for="u_account">Payout account name</label>
                            <input id="u_account" name="payout_account_name" type="text" class="input"
                                   value="{{ old('payout_account_name', $creator->profile->payout_account_name) }}"
                                   maxlength="160">
                        </div>
                        <div class="field">
                            <label for="u_gst">GST number</label>
                            <input id="u_gst" name="gst_number" type="text" class="input"
                                   value="{{ old('gst_number', $creator->profile->gst_number) }}" maxlength="20">
                        </div>
                        <div class="field">
                            <label for="u_pan">PAN</label>
                            <input id="u_pan" name="pan_number" type="text" class="input"
                                   value="{{ old('pan_number', $creator->profile->pan_number) }}" maxlength="20">
                        </div>
                        <div class="field field--wide">
                            <label for="u_payout">Bank / payment details</label>
                            <textarea id="u_payout" name="payout_details" class="input" rows="3"
                                      maxlength="2000">{{ old('payout_details', $creator->profile->payout_details) }}</textarea>
                        </div>
                    </div>

                    <div class="switch-row">
                        <input type="checkbox" id="u_active" name="is_active" value="1"
                               @checked(old('is_active', $creator->is_active))>
                        <div>
                            <label class="switch-row__label" for="u_active">Account active</label>
                            <div class="switch-row__hint">
                                Turn this off to stop them signing in without deleting their data.
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn--dark" style="margin-top: 16px;">Save changes</button>
                </form>
            </div>
        </section>

        {{-- --- History ----------------------------------------------- --}}
        <section class="card">
            <div class="card__head">
                <div>
                    <h2>Sales &amp; settlements</h2>
                    <p class="small muted mb-0">
                        {{ $lifetimeOrders }} successful {{ Str::plural('order', $lifetimeOrders) }} all time.
                    </p>
                </div>
            </div>
            <div class="card__body card__body--flush">
                @if ($settlements->isEmpty())
                    <div class="empty">
                        <h3>No settled sales yet</h3>
                        <p class="small">
                            Settlements appear here once a sale this creator took part in is closed out.
                        </p>
                    </div>
                @else
                    <div class="table-scroll">
                        <table class="data">
                            <thead>
                            <tr>
                                <th>Sale</th>
                                <th class="num">Units</th>
                                <th class="num">Payout</th>
                                <th>Status</th>
                                <th>Paid</th>
                                <th class="shrink"></th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($settlements as $settlement)
                                <tr>
                                    <td>
                                        <div class="who__name">{{ $settlement->sale->name }}</div>
                                        <div class="who__meta">
                                            ended {{ $settlement->sale->ends_at->format('j M Y') }}
                                        </div>
                                    </td>
                                    <td class="num">{{ $settlement->units_sold }}</td>
                                    <td class="num">
                                        <strong>{{ \App\Support\Money::format($settlement->payout_amount, $settlement->currency) }}</strong>
                                    </td>
                                    <td>
                                        <span class="badge badge--{{ $settlement->isPaid() ? 'paid' : ($settlement->hasInvoice() ? 'invoiced' : 'awaiting') }}">
                                            {{ $settlement->stageLabel() }}
                                        </span>
                                    </td>
                                    <td class="muted small">
                                        @if ($settlement->isPaid())
                                            {{ \App\Support\Money::format($settlement->paid_amount, $settlement->currency) }}
                                            on {{ $settlement->paid_on?->format('j M Y') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="shrink">
                                        <a class="btn btn--outline btn--sm"
                                           href="{{ route('admin.creators.dashboard', [$creator, $settlement->sale]) }}">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>

    </div>

@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var button = document.querySelector('[data-copy-password]');
            var value = document.querySelector('[data-password]');
            if (!button || !value) return;

            button.addEventListener('click', function () {
                navigator.clipboard.writeText(value.textContent.trim()).then(function () {
                    button.textContent = 'Copied';
                    window.setTimeout(function () { button.textContent = 'Copy'; }, 2000);
                });
            });
        });
    </script>
@endpush
