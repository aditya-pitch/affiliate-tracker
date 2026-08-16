<x-mail::message>
# Your sign-in code

Hi {{ $name }},

Here is the code to finish signing in to your Pitch Innovations affiliate dashboard.

<x-mail::panel>
# {{ $code }}
</x-mail::panel>

It expires in {{ $minutes }} minutes and can only be used once.

If you did not just try to sign in, someone else may have your password. Please
[reset it]({{ route('password.request') }}) and let us know at
support@pitchinnovations.com.

Thanks,<br>
Pitch Innovations
</x-mail::message>
