<x-mail::message>
# {{ $sale->name }} has ended

Hi {{ $name }},

Thank you for everything you put into this one. Your report is now final and
ready to look at.

You made **{{ $unitsSold }} {{ Str::plural('sale', $unitsSold) }}** with your
coupon {{ Str::plural('code', $unitsSold) }} during this campaign, earning a
payout of **{{ $payout }}**.

<x-mail::button :url="$reportUrl">
View your report
</x-mail::button>

From your dashboard you can download the full report as an Excel file, and
upload your invoice for the commission owed. Once we have your invoice, our team
will process the payment and email you a confirmation.

Thanks again,<br>
Pitch Innovations
</x-mail::message>
