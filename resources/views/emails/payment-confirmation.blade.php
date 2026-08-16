<x-mail::message>
# Your commission has been paid

Hi {{ $name }},

Your commission for **{{ $sale->name }}** is on its way.

<x-mail::panel>
**{{ $amount }}**
@if ($paidOn)
<br>Paid on {{ $paidOn }}
@endif
@if ($reference)
<br>Reference: {{ $reference }}
@endif
</x-mail::panel>

Depending on your bank, it can take a couple of working days to land.

<x-mail::button :url="$reportUrl">
View the sale
</x-mail::button>

Thank you for the work you put into this campaign — we hope to have you on the
next one.

Pitch Innovations
</x-mail::message>
