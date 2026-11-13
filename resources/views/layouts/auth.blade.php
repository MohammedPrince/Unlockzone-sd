{{-- Sign-in, registration and password pages: header bar, title strip, one card, and a drawer menu for guests. --}}
@php
    $site = setting('site_name');
    $isAdminPage = request()->routeIs('admin.*');
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.favicon')
    <title>@yield('title', __('Sign in')) · {{ $site }}</title>
    @include('partials.head-theme')
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ filemtime(public_path('assets/css/app.css')) }}">
</head>
<body class="portal-guest">
@unless ($isAdminPage)
    @include('partials.guest-drawer')
@endunless

<header class="site-header">
    @unless ($isAdminPage)
        <button type="button" class="btn menu-btn always" data-nav-toggle aria-label="{{ __('Open menu') }}" aria-controls="guest-drawer">
            @include('partials.icon', ['name' => 'menu'])
        </button>
    @endunless
    <a class="brand" href="{{ route($isAdminPage ? 'admin.login' : 'home') }}">
        @include('partials.brand-logo', ['small' => $__env->yieldContent('portal', __('CUSTOMER PORTAL'))])
    </a>
    <div class="site-header-tools">@include('partials.top-tools')</div>
</header>

<div class="title-strip"><h1>{{ $__env->hasSection('strip-title') ? $__env->yieldContent('strip-title') : $__env->yieldContent('title', __('Sign in')) }}</h1></div>

<main class="auth">
    <div class="auth-card @yield('card-class')">
        @include('partials.flash')

        @yield('content')
    </div>
    @unless ($isAdminPage)
        <nav class="legal-links auth-legal" aria-label="{{ __('Legal') }}">
            <a href="{{ route('legal.terms') }}">{{ __('Terms of Service') }}</a>
            <span aria-hidden="true">·</span>
            <a href="{{ route('legal.privacy') }}">{{ __('Privacy Policy') }}</a>
        </nav>
    @endunless
</main>
@unless ($isAdminPage)
    @include('partials.chat-float')
@endunless
<script src="{{ asset('assets/js/app.js') }}?v={{ filemtime(public_path('assets/js/app.js')) }}" defer></script>
</body>
</html>
