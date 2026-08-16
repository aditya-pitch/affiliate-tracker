{{--
    The four sign-in steps from spec section 3. Showing where a creator is
    makes an unusually long sign-in feel finite rather than endless.
--}}
@php
    $labels = ['Email', 'Password', 'Date of birth', 'Emailed code'];
@endphp

<div class="steps" role="progressbar" aria-valuenow="{{ $current }}" aria-valuemin="1" aria-valuemax="4"
     aria-label="Sign-in step {{ $current }} of 4">
    @for ($i = 1; $i <= 4; $i++)
        <span class="steps__dot {{ $i < $current ? 'is-done' : ($i === $current ? 'is-current' : '') }}"></span>
    @endfor
    <span class="steps__label">Step {{ $current }} of 4 · {{ $labels[$current - 1] }}</span>
</div>
