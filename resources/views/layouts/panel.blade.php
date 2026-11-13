{{-- Shared shell for the admin control panel and the customer portal. The menu follows the user's role. --}}
@php
    $me = auth()->user();
    $isAdmin = $me->isAdmin();
    $site = setting('site_name');
    $locale = app()->getLocale();
    $rtl = $locale === 'ar';

    $menu = $isAdmin
        ? [
            'Overview' => [
                ['admin.dashboard', 'Dashboard', 'home', 'admin.dashboard'],
            ],
            'Customers' => [
                ['admin.customers.index', 'Customers', 'users', 'admin.customers.*'],
                ['admin.credits.index', 'Credits & ledger', 'wallet', 'admin.credits.*'],
            ],
            'Services' => [
                ['admin.categories.index', 'Categories', 'folder', 'admin.categories.*'],
                ['admin.services.index', 'Services', 'box', 'admin.services.*'],
                ['admin.requests.index', 'Service requests', 'list', 'admin.requests.*'],
                ['admin.providers.index', 'API providers', 'plug', 'admin.providers.*'],
            ],
            'Insights' => [
                ['admin.reports.index', 'Reports', 'chart', 'admin.reports.*'],
                ['admin.activity', 'Activity log', 'clock', 'admin.activity'],
            ],
            'Platform' => [
                ['admin.announcements.index', 'Announcement bar', 'megaphone', 'admin.announcements.*'],
                ['admin.settings', 'Settings', 'cog', 'admin.settings'],
                ['admin.tools.index', 'Server Tools', 'tool', 'admin.tools.*'],
            ],
        ]
        : [
            'My account' => [
                ['customer.dashboard', 'Dashboard', 'home', 'customer.dashboard'],
                ['customer.services.index', 'Services', 'cart', 'customer.services.*'],
                ['customer.requests.index', 'Server Orders', 'list', 'customer.requests.*'],
                ['customer.credits', 'Credit history', 'wallet', 'customer.credits'],
                ['customer.profile', 'Profile', 'user', 'customer.profile'],
            ],
        ];

    $greeting = now()->hour < 12 ? 'Good morning' : (now()->hour < 18 ? 'Good afternoon' : 'Good evening');
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.favicon')
    <title>@yield('title', __('Dashboard')) · {{ $site }}</title>
    @include('partials.head-theme')
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ filemtime(public_path('assets/css/app.css')) }}">
</head>
<body class="{{ $isAdmin ? 'portal-admin' : 'portal-customer' }}">
{{-- Restore a collapsed desktop sidebar before the first paint, so it does not flash open. --}}
<script>try { if (localStorage.getItem('nav') === 'collapsed') { document.body.classList.add('nav-collapsed'); } } catch (e) { /* storage blocked */ }</script>
<div class="shell">
    <aside class="sidebar" id="sidebar">
        @if ($isAdmin)
            <a class="brand" href="{{ route('admin.dashboard') }}">
                @include('partials.brand-logo', ['small' => __('CONTROL PANEL')])
            </a>
        @else
            @include('partials.customer-nav')
        @endif

        @if ($isAdmin)
        <nav class="nav" aria-label="{{ __('Main menu') }}">
            @foreach ($menu as $group => $links)
                <div class="nav-label">{{ __($group) }}</div>
                @foreach ($links as [$route, $label, $icon, $pattern])
                    <a href="{{ route($route) }}" @class(['active' => request()->routeIs($pattern)]) @if (request()->routeIs($pattern)) aria-current="page" @endif>
                        @include('partials.icon', ['name' => $icon])
                        <span>{{ __($label) }}</span>
                        @if ($route === 'admin.requests.index' && ($navPending ?? 0) > 0)
                            <span class="count" title="{{ __('Pending requests') }}">{{ $navPending }}</span>
                        @endif
                    </a>
                @endforeach
            @endforeach
        </nav>

        <div class="sidebar-foot">{{ __('Signed in as') }} {{ $me->username }}</div>
        @endif
    </aside>
    <div class="overlay" data-nav-toggle></div>

    <div class="main">
        <header class="topbar">
            <button type="button" class="btn menu-btn" data-nav-toggle aria-label="{{ __('Open menu') }}" aria-controls="sidebar">
                @include('partials.icon', ['name' => 'menu'])
            </button>

            @if (!$isAdmin)
                <a class="brand topbar-brand" href="{{ route('customer.dashboard') }}">
                    @include('partials.brand-logo')
                </a>
            @endif
            <div class="crumbs">
                <small>{{ $site }} / @yield('title', __('Dashboard'))</small>
                <strong>{{ __($greeting) }}{{ $rtl ? '،' : ',' }} {{ $me->first_name }}</strong>
            </div>

            <span class="chip hide-md">{{ now()->locale($locale)->translatedFormat('D, M j, Y') }}</span>

            @include('partials.top-tools')

            @if (!$isAdmin)
                <a class="chip" href="{{ route('customer.credits') }}" title="{{ __('Your credit balance') }}">
                    @include('partials.icon', ['name' => 'wallet'])
                    <b class="num">{{ money($navBalance ?? 0) }}</b>
                </a>
            @else
                {{-- Notification bell: what customers, providers and other admins did. Refreshed every minute by app.js. --}}
                <details class="notif-menu" data-notifications
                         data-url="{{ route('admin.notifications.index') }}" data-read-url="{{ route('admin.notifications.read') }}">
                    <summary class="btn notif-btn" aria-label="{{ __('Notifications') }}" title="{{ __('Notifications') }}">
                        @include('partials.icon', ['name' => 'bell'])
                        <span class="notif-count" data-notif-count @if (($notifUnread ?? 0) === 0) hidden @endif>{{ ($notifUnread ?? 0) >= 99 ? '99+' : ($notifUnread ?? 0) }}</span>
                    </summary>
                    <div class="dropdown notif-panel">
                        <div class="notif-head">
                            <strong>{{ __('Notifications') }}</strong>
                            <form method="post" action="{{ route('admin.notifications.read') }}">
                                @csrf
                                <button type="submit" class="notif-link">{{ __('Mark all as read') }}</button>
                            </form>
                        </div>
                        <div class="notif-list" data-notif-list>
                            @include('partials.notification-list', ['items' => $notifItems ?? collect(), 'notifier' => app(\App\Services\AdminNotificationService::class), 'admin' => $me])
                        </div>
                        <a class="notif-foot" href="{{ route('admin.activity') }}">{{ __('View the full activity log') }}</a>
                    </div>
                </details>

                <a class="btn btn-primary hide-sm" href="{{ route('admin.credits.create') }}">
                    @include('partials.icon', ['name' => 'plus']) {{ __('Recharge') }}
                </a>
            @endif

            <details class="user-menu">
                <summary class="chip" aria-label="{{ __('Account menu') }}">
                    <span class="avatar">{{ $me->initials() }}</span>
                    <span class="who">{{ $me->username }}<small>{{ $isAdmin ? __('Administrator') : __('Customer') }}</small></span>
                </summary>
                <div class="dropdown">
                    <a href="{{ route($isAdmin ? 'admin.profile' : 'customer.profile') }}">@include('partials.icon', ['name' => 'user']) {{ __('My profile') }}</a>
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit">@include('partials.icon', ['name' => 'logout']) {{ __('Sign out') }}</button>
                    </form>
                </div>
            </details>
        </header>

        @if (!$isAdmin)
            @include('partials.ticker')
        @endif

        <main class="content">
            <div class="page-head">
                <div>
                    <p class="eyebrow">@yield('eyebrow', $isAdmin ? __('Control panel') : __('Customer portal'))</p>
                    <h1>@yield('page-title', __('Dashboard'))</h1>
                    <p class="sub">@yield('page-description')</p>
                </div>
                <div class="actions no-print">@yield('page-actions')</div>
            </div>

            @if (!$isAdmin && setting('announcement'))
                <div class="alert alert-info">{{ setting('announcement') }}</div>
            @endif

            @if (!$isAdmin && $me->services_suspended)
                <div class="alert alert-error" role="status">{{ __('Ordering services is suspended for your account. Please contact support.') }}</div>
            @endif

            @include('partials.flash')

            @yield('content')

            <p class="footer">
                {{ $site }} · {{ now()->year }}
                · <a href="{{ route('legal.terms') }}">{{ __('Terms of Service') }}</a>
                · <a href="{{ route('legal.privacy') }}">{{ __('Privacy Policy') }}</a>
            </p>
        </main>
    </div>
</div>
@if (!$isAdmin)
    @include('partials.chat-float', ['only' => 'whatsapp'])
@endif
<script src="{{ asset('assets/js/app.js') }}?v={{ filemtime(public_path('assets/js/app.js')) }}" defer></script>
@stack('scripts')
</body>
</html>
