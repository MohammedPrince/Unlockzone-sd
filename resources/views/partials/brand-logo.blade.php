{{--
    The logo for headers. With a wide logo uploaded (Admin > Settings) it shows that image, swapping to the
    dark-background version in the dark theme. Without one it falls back to the small badge and the site name.
    Optional: $small (text under the name, e.g. CONTROL PANEL).
--}}
@php
    $wideLight = (string) setting('site_logo_wide');
    $wideDark = (string) setting('site_logo_wide_dark') ?: $wideLight;
    $siteName = setting('site_name');
@endphp
@if ($wideLight !== '' || $wideDark !== '')
    <span class="brand-wide">
        <img class="for-light-theme" src="{{ asset($wideLight ?: $wideDark) }}" alt="{{ $siteName }}">
        <img class="for-dark-theme" src="{{ asset($wideDark) }}" alt="{{ $siteName }}">
        @if (!empty($small)) <small>{{ $small }}</small> @endif
    </span>
@else
    @include('partials.brand-mark')
    <span>{{ $siteName }}@if (!empty($small))<small>{{ $small }}</small>@endif</span>
@endif
