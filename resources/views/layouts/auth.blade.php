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
    @if (request()->routeIs('login', 'register'))
        @include('partials.seo', ['seo' => request()->routeIs('login') ? [
            'title' => __('Login') . ' · ' . $site,
            'description' => __('Sign in to :site to order unlocking services, follow your orders and see your credit.', ['site' => $site]),
        ] : [
            'title' => __('Registration') . ' · ' . $site,
            'description' => __('Create your :site account to order unlocking services online.', ['site' => $site]),
        ]])
    @endif
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
    <a class="brand" href="{{ $isAdminPage ? route('admin.login') : \App\Support\Seo::url('home') }}">
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
            <a href="{{ \App\Support\Seo::url('legal.terms') }}">{{ __('Terms of Service') }}</a>
            <span aria-hidden="true">·</span>
            <a href="{{ \App\Support\Seo::url('legal.privacy') }}">{{ __('Privacy Policy') }}</a>
        </nav>
    @endunless
</main>
@unless ($isAdminPage)
    @include('partials.chat-float')
@endunless
<script src="{{ asset('assets/js/app.js') }}?v={{ filemtime(public_path('assets/js/app.js')) }}" defer></script>
</body>
</html>
