@extends('layouts.auth')

@section('title', 'Password')

@section('content')
    @include('partials.steps', ['current' => 2])

    <h1 class="auth__title">Enter your password</h1>
    <p class="auth__sub">Signing in as {{ $email }}</p>

    <form method="POST" action="{{ route('login.password') }}">
        @csrf

        {{-- Hidden, so password managers can associate the saved credential
             with the account even though the two fields are on separate pages. --}}
        <input type="hidden" name="email" value="{{ $email }}" autocomplete="username">

        <div class="field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" class="input" required autofocus
                   autocomplete="current-password">
        </div>

        <button type="submit" class="btn btn--dark btn--block">Continue</button>
    </form>

    <p class="auth__foot mb-0">
        <a href="{{ route('password.request') }}">Forgotten your password?</a>
        &nbsp;·&nbsp;
        <a href="{{ route('login') }}">Start again</a>
    </p>
@endsection
