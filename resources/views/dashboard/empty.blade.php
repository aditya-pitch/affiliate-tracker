@extends('layouts.app')

@section('title', 'Your dashboard')

@section('content')
    <div class="card">
        <div class="empty">
            <h1>Welcome, {{ auth()->user()->firstName() }}</h1>
            <p>
                Your account is set up and your coupon code is ready to go. As soon as
                the first order is placed with your code, it will appear here — and the
                figures will keep updating while a sale is running.
            </p>

            @if (auth()->user()->couponCodes->isNotEmpty())
                <p style="margin-top: 20px;">
                    @foreach (auth()->user()->couponCodes as $code)
                        <span class="code" style="font-size: 15px; padding: 6px 12px;">{{ $code->code }}</span>
                    @endforeach
                </p>
            @endif

            <p class="small" style="margin-top: 22px;">
                Questions about your code or your rate?
                <a href="mailto:support@pitchinnovations.com">Get in touch</a>.
            </p>
        </div>
    </div>
@endsection
