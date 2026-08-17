<x-mail::message>
# {{ $headline }}

<x-mail::table>
| | |
|:--|:--|
@foreach ($facts as $label => $value)
| **{{ $label }}** | {{ $value }} |
@endforeach
</x-mail::table>

@if ($actionUrl)
<x-mail::button :url="$actionUrl">
{{ $actionLabel }}
</x-mail::button>
@endif

<small>Internal notification from the affiliate dashboard.</small>
</x-mail::message>
