<x-mail::message>
# {{ $heading }}

@foreach ($lines as $line)
{{ $line }}

@endforeach
@if ($details)
<x-mail::table>
| | |
|:--|:--|
@foreach ($details as $label => $value)
| **{{ $label }}** | {{ str_replace(['|', "\n", "\r"], ['/', ' ', ' '], (string) $value) }} |
@endforeach
</x-mail::table>
@endif

@if ($code !== null && $code !== '')
<x-mail::panel>
{{ $code }}
</x-mail::panel>
@endif

@if ($buttonText && $buttonUrl)
<x-mail::button :url="$buttonUrl" color="primary">
{{ $buttonText }}
</x-mail::button>
@endif
</x-mail::message>
