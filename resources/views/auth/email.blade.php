@extends('layouts.auth')

@section('title', 'Sign in')

@section('content')
    @include('partials.steps', ['current' => 1])

    <h1 class="auth__title">Sign in</h1>
    <p class="auth__sub">Use the email on your Pitch Innovations account.</p>

    <form method="POST" action="{{ route('login.email') }}">
        @csrf

        <div class="field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" class="input" required autofocus
                   autocomplete="username" value="{{ old('email') }}" placeholder="you@example.com">
        </div>

        <button type="submit" class="btn btn--dark btn--block">Continue</button>
    </form>

    <p class="auth__foot mb-0">
        <a href="{{ route('password.request') }}">Forgotten your password?</a>
    </p>
@endsection
