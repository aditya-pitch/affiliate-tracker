@extends('layouts.auth')

@section('title', 'Enter your code')

@section('content')
    @include('partials.steps', ['current' => 4])

    <h1 class="auth__title">Check your email</h1>
    <p class="auth__sub">
        We have sent a {{ config('affiliate.otp.length', 6) }}-digit code to
        <strong>{{ $maskedEmail }}</strong>. It expires in
        {{ config('affiliate.otp.ttl_minutes', 10) }} minutes.
    </p>

    <form method="POST" action="{{ route('login.code') }}">
        @csrf

        <div class="field">
            <label for="code" class="sr-only">One-time code</label>
            <input id="code" name="code" type="text" class="input input--code" required autofocus
                   inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code"
                   maxlength="{{ config('affiliate.otp.length', 6) }}"
                   placeholder="{{ str_repeat('0', config('affiliate.otp.length', 6)) }}">
        </div>

        <button type="submit" class="btn btn--dark btn--block">Sign in</button>
    </form>

    <form method="POST" action="{{ route('login.code.resend') }}" style="margin-top: 14px;">
        @csrf
        <button type="submit" class="btn btn--outline btn--block btn--sm"
                @disabled($secondsUntilResend > 0)>
            @if ($secondsUntilResend > 0)
                Send a new code in {{ $secondsUntilResend }}s
            @else
                Send me a new code
            @endif
        </button>
    </form>

    <p class="auth__foot mb-0">
        <a href="{{ route('login') }}">Start again</a>
    </p>
@endsection
