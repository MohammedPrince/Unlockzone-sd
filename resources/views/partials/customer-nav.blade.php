{{--
    The customer's menu, laid out like a Dhru Fusion client area:
    profile head, Client Area, Place an order (by category), Order history (by category), My account, Logout.
    Expects $me; uses $navCategories, $navBalance and $navLastLogin from the panel view composer.
--}}
@php
    $categories = $navCategories ?? collect();
    $currentCategory = (int) request()->query('category');
    $whatsapp = setting('whatsapp_enabled') === '1' ? preg_replace('/[^0-9]+/', '', (string) setting('whatsapp_number')) : '';
    // "+ Add funds" opens the admin's WhatsApp when it is switched on, otherwise the statement page.
    $fundsLink = $whatsapp !== '' ? 'https://wa.me/' . $whatsapp : route('customer.credits');
    $fundsExternal = $whatsapp !== '';
    $item = fn (bool $active) => $active ? 'cnav-item active' : 'cnav-item';
@endphp

<div class="cnav-head">
    <button type="button" class="cnav-back" data-nav-toggle aria-label="{{ __('Close menu') }}">@include('partials.icon', ['name' => 'back'])</button>
    <a class="cnav-user" href="{{ route('customer.profile') }}">
        <span class="cnav-avatar">{{ $me->initials() }}</span>
        <span class="cnav-who">
            <b>{{ trim($me->first_name . ' ' . $me->last_name) ?: $me->username }}</b>
            <small dir="ltr">@if ($me->customer_tag)<span class="cnav-tag">({{ $me->customer_tag }})</span> @endif{{ $me->email }}</small>
        </span>
    </a>
</div>

<nav class="cnav" aria-label="{{ __('Main menu') }}">
    <a class="{{ $item(request()->routeIs('customer.dashboard')) }}" href="{{ route('customer.dashboard') }}">
        <span>{{ __('Client Area') }}</span>
        <b class="cnav-balance num">{{ money($navBalance ?? 0) }}</b>
    </a>

    <p class="cnav-label">{{ __('Place an order') }}</p>
    <a class="{{ $item(request()->routeIs('customer.services.*') && !$currentCategory) }}" href="{{ route('customer.services.index') }}"><span>{{ __('All services') }}</span></a>
    @foreach ($categories as $category)
        <a class="{{ $item(request()->routeIs('customer.services.index') && $currentCategory === $category->id) }}" href="{{ route('customer.services.index', ['category' => $category->id]) }}"><span dir="auto">{{ $category->name }}</span></a>
    @endforeach

    <p class="cnav-label">{{ __('Order history') }}</p>
    @foreach ($categories as $category)
        <a class="{{ $item(request()->routeIs('customer.requests.index') && $currentCategory === $category->id) }}" href="{{ route('customer.requests.index', ['category' => $category->id]) }}"><span dir="auto">{{ $category->name }}</span></a>
    @endforeach
    <a class="{{ $item(request()->routeIs('customer.requests.*') && !$currentCategory) }}" href="{{ route('customer.requests.index') }}"><span>{{ __('Server Orders') }}</span></a>

    <p class="cnav-label">{{ __('My account') }}</p>
    <a class="{{ $item(request()->routeIs('customer.profile')) }}" href="{{ route('customer.profile') }}"><span>{{ __('My Profile') }}</span></a>
    <a class="cnav-item" href="{{ $fundsLink }}" @if ($fundsExternal) target="_blank" rel="noopener noreferrer" @endif><span>{{ __('+ Add Funds') }}</span></a>
    <a class="{{ $item(request()->routeIs('customer.credits')) }}" href="{{ route('customer.credits') }}"><span>{{ __('My Statement') }}</span></a>
    <a class="{{ $item(request()->routeIs('customer.guide')) }}" href="{{ route('customer.guide') }}"><span>{{ __('User Guide') }}</span></a>
    @if (setting('show_terms_link') !== '0')
        <a class="{{ $item(request()->routeIs('legal.terms')) }}" href="{{ route('legal.terms') }}"><span>{{ __('Terms of Service') }}</span></a>
    @endif
    @if (setting('show_privacy_link') !== '0')
        <a class="{{ $item(request()->routeIs('legal.privacy')) }}" href="{{ route('legal.privacy') }}"><span>{{ __('Privacy Policy') }}</span></a>
    @endif
    <div class="cnav-item cnav-static">
        <span>{{ __('Last Login') }}
            <small>
                @if ($navLastLogin ?? null)
                    <span dir="ltr">{{ $navLastLogin->created_at?->format('m/d/y, h:i A') }}</span><br>
                    <span dir="ltr">IP: {{ $navLastLogin->ip_address ?: '-' }}</span>
                @else
                    {{ __('This is your first sign-in.') }}
                @endif
            </small>
        </span>
    </div>
    <form method="post" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="cnav-item cnav-logout"><span>{{ __('Logout') }}</span></button>
    </form>
</nav>
