{{--
    Public page for visitors (Terms, Privacy): the same header and slide-out menu as the sign-in pages,
    a title strip, the content and the site footer. Signed-in users see these pages inside their normal panel instead.
--}}
@php
    $site = setting('site_name');
    $locale = app()->getLocale();
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    @include('partials.favicon')
    <title>@yield('title') · {{ $site }}</title>
    @include('partials.head-theme')
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ filemtime(public_path('assets/css/app.css')) }}">
</head>
<body class="portal-guest public-page">
@include('partials.guest-drawer')

<header class="site-header">
    <button type="button" class="btn menu-btn" data-nav-toggle aria-label="{{ __('Open menu') }}" aria-controls="guest-drawer">
        @include('partials.icon', ['name' => 'menu'])
    </button>
    <a class="brand" href="{{ route('home') }}">
        @include('partials.brand-logo')
    </a>
    <div class="site-header-tools">
        <span class="hide-sm">@include('partials.top-tools')</span>
        @if (setting('allow_registration') === '1')
            <a class="btn hide-sm" href="{{ route('register') }}">{{ __('Registration') }}</a>
        @endif
        <a class="btn btn-primary" href="{{ route('login') }}">@include('partials.icon', ['name' => 'lock']) {{ __('Login') }}</a>
    </div>
</header>

<div class="title-strip"><h1>@yield('title')</h1></div>

<main class="legal-main">
    <div class="legal-tools show-sm">@include('partials.top-tools')</div>
    @yield('content')
</main>

@include('partials.legal-footer')
@include('partials.chat-float')
<script src="{{ asset('assets/js/app.js') }}?v={{ filemtime(public_path('assets/js/app.js')) }}" defer></script>
</body>
</html>
