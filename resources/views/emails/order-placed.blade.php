<x-mail::message>
# Someone just used your code

Hi {{ $name }},

Nice one — a new sale came in on **{{ $code }}**.

<x-mail::panel>
**{{ $plugin }}** · {{ $country }}<br>
You earned about **{{ $earned }}** from this one.
</x-mail::panel>

Your dashboard is updating live if you want to watch the rest of the sale come in.

<x-mail::button :url="$dashboardUrl">
Open your dashboard
</x-mail::button>

Keep it going,<br>
Pitch Innovations

<small>Getting a lot of these? You can switch to an hourly or daily summary in your
dashboard settings.</small>
</x-mail::message>
