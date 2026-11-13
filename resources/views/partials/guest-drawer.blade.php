{{-- The visitors' slide-out menu (sign-in pages, Terms, Privacy): orange head, Registration / Login, categories, legal links. --}}
@php
    try {
        $guestCategories = \App\Models\ServiceCategory::where('status', 'active')->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
    } catch (\Throwable) {
        $guestCategories = collect();
    }
@endphp
<aside class="drawer" id="guest-drawer" aria-label="{{ __('Main menu') }}">
    <div class="drawer-head">
        <button type="button" class="drawer-close" data-nav-toggle aria-label="{{ __('Close menu') }}">@include('partials.icon', ['name' => 'back'])</button>
        <strong>{{ mb_strtoupper(setting('site_name')) }}</strong>
    </div>
    <div class="drawer-auth">
        @if (setting('allow_registration') === '1')
            <a class="btn btn-light" href="{{ route('register') }}">{{ __('Registration') }}</a>
        @endif
        <a class="btn btn-dark" href="{{ route('login') }}">{{ __('Login') }}</a>
    </div>
    <nav class="drawer-nav">
        <a href="{{ route('home') }}">{{ __('Home') }} <span aria-hidden="true">›</span></a>
    </nav>
    @if ($guestCategories->isNotEmpty())
        <p class="drawer-label">{{ __('Reseller pricing') }}</p>
        <nav class="drawer-nav">
            @foreach ($guestCategories as $category)
                <a href="{{ route('login') }}">{{ $category->name }} <span aria-hidden="true">›</span></a>
            @endforeach
        </nav>
    @endif
    <p class="drawer-label">{{ __('Legal') }}</p>
    <nav class="drawer-nav">
        <a href="{{ route('legal.terms') }}" @if (request()->routeIs('legal.terms')) aria-current="page" @endif>{{ __('Terms of Service') }} <span aria-hidden="true">›</span></a>
        <a href="{{ route('legal.privacy') }}" @if (request()->routeIs('legal.privacy')) aria-current="page" @endif>{{ __('Privacy Policy') }} <span aria-hidden="true">›</span></a>
    </nav>
</aside>
<div class="overlay" data-nav-toggle></div>
