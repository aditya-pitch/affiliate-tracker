<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Sign in') · Pitch Innovations Affiliates</title>
    <link rel="icon" href="{{ asset('assets/img/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
</head>
<body>
<div class="auth">

    <div class="auth__logo">
        <img src="{{ asset('assets/img/logo.png') }}" alt="Pitch Innovations">
    </div>

    <div class="auth__card">
        @include('partials.flash')
        @yield('content')
    </div>

    <p class="auth__foot">
        Trouble signing in? Email
        <a href="mailto:support@pitchinnovations.com">support@pitchinnovations.com</a>
    </p>

</div>
</body>
</html>
