{{--
    Link preview tags (WhatsApp, Telegram, Facebook, X ...): the site name and tagline with the square logo.
    Used by the home page and the maintenance page, so a shared link looks the same in both cases.
--}}
@php
    $shareSite = (string) setting('site_name');
    $shareTagline = trim((string) setting('home_tagline'));
    $shareTitle = $shareTagline !== '' ? $shareSite . ' · ' . $shareTagline : $shareSite;
    $shareLogo = (string) setting('site_logo');
    $shareImage = $shareLogo !== '' && is_file(public_path($shareLogo)) ? asset($shareLogo) : null;
@endphp
@if ($shareTagline !== '')
    <meta name="description" content="{{ $shareTagline }}">
@endif
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ $shareSite }}">
<meta property="og:title" content="{{ $shareTitle }}">
@if ($shareTagline !== '')
    <meta property="og:description" content="{{ $shareTagline }}">
@endif
<meta property="og:url" content="{{ url('/') }}">
@if ($shareImage)
    <meta property="og:image" content="{{ $shareImage }}">
    <meta name="twitter:image" content="{{ $shareImage }}">
@endif
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="{{ $shareTitle }}">
