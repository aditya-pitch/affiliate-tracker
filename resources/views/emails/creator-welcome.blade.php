<x-mail::message>
# Your dashboard is ready

Hi {{ $name }},

Welcome aboard. Your affiliate dashboard is set up, so you can watch how your
coupon {{ count($codes) === 1 ? 'code performs' : 'codes perform' }} during a
sale and see exactly what you have earned, updated live.

## Your coupon {{ count($codes) === 1 ? 'code' : 'codes' }}

<x-mail::panel>
@foreach ($codes as $code)
**{{ $code }}**@if (! $loop->last)<br>@endif
@endforeach
</x-mail::panel>

You earn **{{ $rate }}** commission, paid in **{{ $currency }}**.

## Signing in

There are four steps, in this order:

1. **Email** — {{ $email }}
2. **Password** — @if ($password) `{{ $password }}` @else the password we sent you separately @endif
3. **Date of birth** — {{ $dateOfBirth ?? 'the date of birth on your account' }}
4. **A 6-digit code** we email you at that moment — this is the step that actually
   secures your account, so keep an eye on your inbox

<x-mail::button :url="$loginUrl">
Sign in to your dashboard
</x-mail::button>

@if ($password)
Please change your password once you are in — you can do that under Settings.
@endif

A few things worth knowing:

- The dashboard signs you out after 20 minutes of inactivity, because it shows
  earnings. Just sign in again.
- While a sale is running your numbers update on their own — no need to refresh.
- When a sale ends we email you, your report is finalised, and you can download
  it and upload your invoice.

Any trouble at all, reply to this email or write to support@pitchinnovations.com.

Glad to have you with us,<br>
Pitch Innovations
</x-mail::message>
