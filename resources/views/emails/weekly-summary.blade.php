<x-mail::message>
# Your week: {{ $weekLabel }}

Hi {{ $name }},

Here is how your {{ $summary->unitsSold === 1 ? 'code' : 'codes' }} did this week.

<x-mail::table>
| | |
|:--|--:|
@foreach ($summary->rows() as $row)
| {{ $row['label'] }} | {{ $row['value'] }} |
@endforeach
</x-mail::table>

@if ($encouragement)
<x-mail::panel>
{{ $encouragement }}
</x-mail::panel>
@endif

<x-mail::button :url="$dashboardUrl">
Open your dashboard
</x-mail::button>

Thanks for everything you are putting out there.

Pitch Innovations

<small>You can turn these weekly emails off in your dashboard settings.</small>
</x-mail::message>
