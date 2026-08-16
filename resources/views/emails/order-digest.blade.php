<x-mail::message>
# {{ $count }} new {{ Str::plural('sale', $count) }} on your code

Hi {{ $name }},

Here is what came in {{ $period }}. You earned about **{{ $earned }}** from these.

<x-mail::table>
| Plugin | Code | Country |
|:-------|:-----|:--------|
@foreach ($orders as $order)
| {{ $order->plugin }} | {{ $order->couponCode->code }} | {{ $order->country }} |
@endforeach
</x-mail::table>

<x-mail::button :url="$dashboardUrl">
See the full picture
</x-mail::button>

Your audience is clearly listening. Keep the momentum going.

Pitch Innovations
</x-mail::message>
