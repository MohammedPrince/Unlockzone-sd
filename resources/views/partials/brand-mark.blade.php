{{-- The site logo when one is uploaded (Admin > Settings), otherwise the first letter of the site name. --}}
@php $logo = setting('site_logo'); @endphp
@if ($logo)
    <span class="brand-mark has-logo"><img src="{{ asset($logo) }}" alt="{{ setting('site_name') }}"></span>
@else
    <span class="brand-mark">{{ strtoupper(mb_substr(setting('site_name'), 0, 1)) }}</span>
@endif
