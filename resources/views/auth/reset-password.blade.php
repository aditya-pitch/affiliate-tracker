@extends('layouts.auth')

@section('title', 'Set a new password')

@section('content')
    <h1 class="auth__title">Set a new password</h1>
    <p class="auth__sub">Choose something at least 10 characters long, with letters and numbers.</p>

    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" class="input" required
                   autocomplete="username" value="{{ old('email', $email) }}">
        </div>

        <div class="field">
            <label for="password">New password</label>
            <input id="password" name="password" type="password" class="input" required autofocus
                   autocomplete="new-password">
        </div>

        <div class="field">
            <label for="password_confirmation">Confirm new password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" class="input" required
                   autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn--dark btn--block">Save new password</button>
    </form>

    <p class="auth__foot mb-0">
        You will still be asked for your date of birth and an emailed code the next time you sign in.
    </p>
@endsection
