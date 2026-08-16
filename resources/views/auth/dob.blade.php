@extends('layouts.auth')

@section('title', 'Confirm your details')

@section('content')
    @include('partials.steps', ['current' => 3])

    <h1 class="auth__title">Confirm your date of birth</h1>
    <p class="auth__sub">A quick check that this is you.</p>

    <form method="POST" action="{{ route('login.dob') }}">
        @csrf

        <div class="field">
            <label for="date_of_birth">Date of birth</label>
            <input id="date_of_birth" name="date_of_birth" type="date" class="input" required autofocus
                   max="{{ now()->subYear()->toDateString() }}">
            <p class="hint">The date of birth on your affiliate account.</p>
        </div>

        <button type="submit" class="btn btn--dark btn--block">Continue</button>
    </form>

    <p class="auth__foot mb-0">
        <a href="{{ route('login') }}">Start again</a>
    </p>
@endsection
