<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Affiliate Dashboard') · Pitch Innovations</title>
    <link rel="icon" href="{{ asset('assets/img/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
</head>
<body>
<div class="shell">

    <header class="masthead">
        <div class="wrap">
            <div class="masthead__logo">
                <a href="{{ route('dashboard') }}" aria-label="Pitch Innovations affiliate dashboard">
                    <img src="{{ asset('assets/img/logo.png') }}" alt="Pitch Innovations">
                </a>
                <span class="masthead__label">Affiliate Dashboard</span>
            </div>

            <div class="masthead__right">
                @auth
                    <span class="masthead__who">{{ auth()->user()->firstName() }}</span>

                    @if (auth()->user()->isAdmin())
                        <a class="ghost" href="{{ route('admin.settlements.index') }}">Settlements</a>
                    @else
                        <a class="ghost" href="{{ route('settings') }}">Settings</a>
                    @endif

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="ghost">Sign out</button>
                    </form>
                @endauth
            </div>
        </div>
    </header>

    <main class="page">
        <div class="wrap">
            @include('partials.flash')
            @yield('content')
        </div>
    </main>

</div>

@stack('scripts')
</body>
</html>
