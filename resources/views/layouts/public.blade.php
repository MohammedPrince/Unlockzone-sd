{{--
    Public page for visitors (Terms, Privacy, the public service pages): the same header and slide-out menu as the sign-in pages,
    a title strip, the content and the site footer. Signed-in users see these pages inside their normal panel instead.
--}}
@php
    $site = setting('site_name');
    $locale = app()->getLocale();
    // Pages without their own search texts (Terms, Privacy) use their title and first paragraph.
    $seo ??= ['title' => html_entity_decode($__env->yieldContent('title'), ENT_QUOTES) . ' · ' . $site, 'description' => \Illuminate\Support\Str::limit($intro ?? '', 155)];
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    @include('partials.favicon')
    <title>{{ $seo['title'] }}</title>
    @include('partials.seo')
    @include('partials.head-theme')
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ filemtime(public_path('assets/css/app.css')) }}">
</head>
<body class="portal-guest public-page">
@include('partials.guest-drawer')

<header class="site-header">
    <button type="button" class="btn menu-btn" data-nav-toggle aria-label="{{ __('Open menu') }}" aria-controls="guest-drawer">
        @include('partials.icon', ['name' => 'menu'])
    </button>
    <a class="brand" href="{{ \App\Support\Seo::url('home') }}">
        @include('partials.brand-logo')
    </a>
    <div class="site-header-tools">
        <span class="hide-sm">@include('partials.top-tools')</span>
        @if (auth()->check())
            <a class="btn btn-primary" href="{{ route(auth()->user()->isAdmin() ? 'admin.dashboard' : 'customer.dashboard') }}">{{ __('Dashboard') }}</a>
        @endif
        @if (auth()->guest() && setting('allow_registration') === '1')
            <a class="btn hide-sm" href="{{ route('register') }}">{{ __('Registration') }}</a>
        @endif
        @guest
            <a class="btn btn-primary" href="{{ route('login') }}">@include('partials.icon', ['name' => 'lock']) {{ __('Login') }}</a>
        @endguest
    </div>
</header>

<div class="title-strip"><h1 dir="auto">@yield('title')</h1></div>

<main class="legal-main @yield('main-class')">
    <div class="legal-tools show-sm">@include('partials.top-tools')</div>
    @yield('content')
</main>

@include('partials.legal-footer')
@include('partials.chat-float')
<script src="{{ asset('assets/js/app.js') }}?v={{ filemtime(public_path('assets/js/app.js')) }}" defer></script>
</body>
</html>
