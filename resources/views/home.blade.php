{{--
    Public home page for visitors (the "/" address). Mobile first: one column on phones, more on wider screens.
    Every text block, the popup, the offer and the contact details are set in Admin > Settings > Home page.
--}}
@php
    $site = setting('site_name');
    $locale = app()->getLocale();
    $phone = trim((string) setting('contact_phone'));
    $email = trim((string) setting('contact_email'));
    $whatsapp = setting('whatsapp_enabled') === '1' ? preg_replace('/[^0-9]+/', '', (string) setting('whatsapp_number')) : '';
    $telegram = setting('telegram_enabled') === '1' ? ltrim((string) setting('telegram_username'), '@') : '';
    $canRegister = setting('allow_registration') === '1';

    $offerOn = setting('home_offer_enabled') === '1' && setting('home_offer_title');
    $offerEnds = null;
    if ($offerOn && setting('home_offer_ends_at')) {
        try { $offerEnds = \Illuminate\Support\Carbon::parse(setting('home_offer_ends_at')); } catch (\Throwable) { $offerEnds = null; }
        // An offer whose end time has passed is hidden automatically.
        if ($offerEnds && $offerEnds->isPast()) { $offerOn = false; }
    }

    $popupOn = setting('home_popup_enabled') === '1';
    $popupText = trim((string) setting('home_popup_text'));
    $features = $categories->take(3);
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="{{ setting('home_tagline') }}">
    <meta name="theme-color" content="#0a1424">
    @include('partials.favicon')
    <title>{{ $site }} · {{ setting('home_tagline') }}</title>
    @include('partials.head-theme')
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ filemtime(public_path('assets/css/app.css')) }}">
</head>
<body class="home-page">
@include('partials.ticker')

{{-- Contact strip: phone and email on the left, language and theme on the right --}}
<div class="home-strip">
    <div class="home-wrap home-strip-inner">
        <div class="home-contacts">
            @if ($phone !== '')
                <a href="tel:{{ preg_replace('/[^0-9+]+/', '', $phone) }}" dir="ltr">@include('partials.icon', ['name' => 'phone']) {{ $phone }}</a>
            @endif
            @if ($email !== '')
                <a href="mailto:{{ $email }}" dir="ltr">@include('partials.icon', ['name' => 'mail']) {{ $email }}</a>
            @endif
        </div>
        <div class="home-tools">@include('partials.top-tools')</div>
    </div>
</div>

{{-- Header: logo, links, sign-in --}}
<header class="home-header">
    <div class="home-wrap home-header-inner">
        <a class="brand" href="{{ route('home') }}">
            @include('partials.brand-logo')
        </a>
        <nav class="home-nav" aria-label="{{ __('Main menu') }}">
            @if ($categories->isNotEmpty()) <a class="hide-sm" href="#services">{{ __('Services') }}</a> @endif
            @if ($canRegister) <a class="btn" href="{{ route('register') }}">{{ __('Registration') }}</a> @endif
            <a class="btn btn-primary" href="{{ route('login') }}">@include('partials.icon', ['name' => 'lock']) {{ __('Login') }}</a>
        </nav>
    </div>
</header>

<main class="home-wrap home-main">
    @if ($offerOn)
        <section class="home-offer" aria-label="{{ __('Offer') }}">
            <div>
                <p class="home-kicker">{{ __('Today flash offer') }}</p>
                <h2 dir="auto">{{ setting('home_offer_title') }}</h2>
                @if (setting('home_offer_text')) <p class="muted" dir="auto">{{ setting('home_offer_text') }}</p> @endif
            </div>
            @if ($offerEnds)
                <div class="home-countdown" data-countdown="{{ $offerEnds->toIso8601String() }}">
                    <small>{{ __('Ends in') }}</small>
                    <b class="num" data-countdown-text>--:--:--</b>
                </div>
            @endif
        </section>
    @endif

    {{-- Welcome card --}}
    <section class="home-hero">
        <div class="home-hero-top">
            <span class="home-pill">{{ mb_strtoupper($site) }}</span>
            <span class="home-pill online"><i></i> {{ __('Online 24/7') }}</span>
        </div>
        <div class="home-hero-body">
            @include('partials.brand-mark')
            <h1>{{ __('Welcome to :site', ['site' => $site]) }}</h1>
            <p dir="auto">{{ setting('home_welcome') }}</p>
            <div class="home-cta">
                @if ($canRegister)
                    <a class="btn btn-primary btn-lg" href="{{ route('register') }}">@include('partials.icon', ['name' => 'user']) {{ __('Create an account') }}</a>
                @endif
                <a class="btn btn-lg" href="{{ route('login') }}">@include('partials.icon', ['name' => 'lock']) {{ __('Login') }}</a>
            </div>
            @if ($whatsapp !== '' || $telegram !== '')
                <div class="home-cta chat">
                    @if ($whatsapp !== '')
                        <a class="btn btn-contact wa" href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener noreferrer">@include('partials.icon', ['name' => 'whatsapp']) {{ __('Chat on WhatsApp') }}</a>
                    @endif
                    @if ($telegram !== '')
                        <a class="btn btn-contact tg" href="https://t.me/{{ $telegram }}" target="_blank" rel="noopener noreferrer">@include('partials.icon', ['name' => 'telegram']) {{ __('Chat on Telegram') }}</a>
                    @endif
                </div>
            @endif
        </div>
    </section>

    @if (setting('announcement'))
        <section class="home-announce" dir="auto">{{ setting('announcement') }}</section>
    @endif

    {{-- What we sell --}}
    <section class="home-services" id="services">
        <h2>{{ mb_strtoupper($site) }}</h2>
        <p class="muted" dir="auto">{{ setting('home_tagline') }}</p>

        @if ($categories->isEmpty())
            <p class="muted" style="margin-top: 18px">{{ __('Services will be listed here soon.') }}</p>
        @else
            <div class="home-cats">
                @foreach ($categories as $category)
                    <a class="home-cat" href="{{ route('login') }}">
                        <h3 dir="auto">{{ $category->name }}</h3>
                        @if ($category->description) <p dir="auto">{{ \Illuminate\Support\Str::limit($category->description, 110) }}</p> @endif
                        <div class="home-cat-foot">
                            <span>{{ trans_choice('{1} :count service|[2,*] :count services', $category->services_count, ['count' => $category->services_count]) }}</span>
                            @if ($category->services_min_price !== null)
                                <b class="num">{{ __('From :price', ['price' => price($category->services_min_price)]) }}</b>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
</main>

@include('partials.legal-footer')

{{-- Notice-board popup, shown once per visit. Turned on / off and written in Admin > Settings. --}}
@if ($popupOn)
    <div class="home-modal" data-home-popup hidden>
        <div class="home-modal-box" role="dialog" aria-modal="true" aria-labelledby="popup-title">
            <button type="button" class="home-modal-close" data-popup-close>{{ __('Close') }}</button>
            <div class="home-notice">
                @if (setting('home_popup_badge')) <span class="home-notice-badge" dir="auto">{{ setting('home_popup_badge') }}</span> @endif
                <h2 id="popup-title">{{ mb_strtoupper($site) }}</h2>
                @if (setting('home_popup_subtitle')) <p class="home-notice-sub" dir="auto">{{ setting('home_popup_subtitle') }}</p> @endif

                @if ($popupText !== '')
                    <div class="home-notice-text" dir="auto">{!! nl2br(e($popupText)) !!}</div>
                @else
                    <div class="home-notice-text" dir="auto"><b>{{ __('Welcome to :site', ['site' => $site]) }}</b><br>{{ setting('home_welcome') }}</div>
                @endif

                @if ($features->isNotEmpty())
                    <div class="home-notice-grid">
                        @foreach ($features as $i => $category)
                            <div class="home-notice-item f{{ $i }}">
                                <b dir="auto">{{ $category->name }}</b>
                                <span dir="auto">{{ $category->description ? \Illuminate\Support\Str::limit($category->description, 70) : trans_choice('{1} :count service|[2,*] :count services', $category->services_count, ['count' => $category->services_count]) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                <p class="home-notice-foot">{{ __('Connect to our API · Enjoy stability · Grow your business') }}</p>
            </div>
        </div>
    </div>
@endif

@include('partials.chat-float')
<script src="{{ asset('assets/js/app.js') }}?v={{ filemtime(public_path('assets/js/app.js')) }}" defer></script>
</body>
</html>
