@extends('layouts.auth')

@section('title', 'Reset your password')

@section('content')
    <h1 class="auth__title">Reset your password</h1>
    <p class="auth__sub">
        Enter the email on your affiliate account and we will send you a link to set a new password.
    </p>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" class="input" required autofocus
                   autocomplete="username" value="{{ old('email') }}">
        </div>

        <button type="submit" class="btn btn--dark btn--block">Send reset link</button>
    </form>

    <p class="auth__foot mb-0">
        <a href="{{ route('login') }}">Back to sign in</a>
    </p>
@endsection
