{{--
    Link preview tags (WhatsApp, Telegram, Facebook, X ...): the site name and tagline with the square logo.
    Used by the home page and the maintenance page, so a shared link looks the same in both cases.
    Public pages include partials.seo, which adds the search engine tags and can set $seo (title, description).
--}}
@php
    $shareSite = (string) setting('site_name');
    $shareTagline = \App\Support\Seo::text('home_tagline');
    $shareTitle = $seo['title'] ?? ($shareTagline !== '' ? $shareSite . ' · ' . $shareTagline : $shareSite);
    $shareTagline = $seo['description'] ?? $shareTagline;
    $shareLogo = (string) setting('site_logo');
    $shareImage = $shareLogo !== '' && is_file(public_path($shareLogo)) ? asset($shareLogo) : null;
    $googleVerify = (string) setting('google_site_verification');
@endphp
@if ($googleVerify !== '')
    <meta name="google-site-verification" content="{{ $googleVerify }}">
@endif
@if ($shareTagline !== '')
    <meta name="description" content="{{ $shareTagline }}">
@endif
<meta property="og:type" content="website">
<meta property="og:locale" content="{{ app()->getLocale() === 'ar' ? 'ar_AR' : 'en_US' }}">
<meta property="og:site_name" content="{{ $shareSite }}">
<meta property="og:title" content="{{ $shareTitle }}">
@if ($shareTagline !== '')
    <meta property="og:description" content="{{ $shareTagline }}">
@endif
<meta property="og:url" content="{{ $seoCanonical ?? route('home') }}">
@if ($shareImage)
    <meta property="og:image" content="{{ $shareImage }}">
    <meta name="twitter:image" content="{{ $shareImage }}">
@endif
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="{{ $shareTitle }}">
