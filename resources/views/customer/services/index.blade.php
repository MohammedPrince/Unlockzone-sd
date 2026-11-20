@extends('layouts.panel')

@section('title', __('Services'))
@section('eyebrow', __('Catalog'))
@section('page-title', __('Services'))
@section('page-description', __('Choose a service to see what it needs and place an order. Prices are in dollars.'))

@section('content')
    <div class="card">
        <form class="filters" method="get" action="{{ route('customer.services.index') }}" style="border-bottom: 0" data-live-search="#catalog-results">
            @if (request()->filled('category')) <input type="hidden" name="category" value="{{ request()->integer('category') }}"> @endif
            <div class="field grow">
                <label for="q">{{ __('Search services') }}</label>
                <input type="search" id="q" name="q" value="{{ $q }}" placeholder="{{ __('Service name') }}" autocomplete="off">
            </div>
            <div class="btns">
                <button class="btn btn-primary" type="submit">{{ __('Search') }}</button>
                @if ($q) <a class="btn" href="{{ route('customer.services.index') }}">{{ __('Clear') }}</a> @endif
            </div>
        </form>
    </div>

    <div id="catalog-results" data-live-results>
    @if ($catalog->isEmpty())
        <div class="card" style="margin-top: 18px">
            <div class="empty"><strong>{{ $q ? __('No services match your search') : __('No services available yet') }}</strong>{{ $q ? __('Try a different name.') : __('Please check back soon.') }}</div>
        </div>
    @endif

    @php
        // Favourites first (page 1 only), then this page's services under their category headings.
        $sections = $sections->map(fn ($s) => $s + ['star' => false]);
        if ($favorites->isNotEmpty()) {
            $sections = $sections->prepend(['key' => 'favorites', 'title' => __('Frequently used'), 'note' => null, 'services' => $favorites, 'total' => $favorites->count(), 'star' => true]);
        }
    @endphp

    {{-- Infinite scroll: the next 20 are appended here. A section with the same data-infinite-key as the last one
         (its category continues) has its cards added to that section's [data-infinite-merge] grid instead. --}}
    <div data-infinite-items>
    @foreach ($sections as $section)
        <section data-infinite-key="{{ $section['key'] }}">
        <div class="section-title">
            <h2>@if ($section['star'])<span class="fav-star">@include('partials.icon', ['name' => 'star'])</span> @endif{{ $section['title'] }}</h2>
            <span class="muted">{{ trans_choice('{1} :count service|[2,*] :count services', $section['total'], ['count' => $section['total']]) }}</span>
        </div>
        @if ($section['note']) <p class="muted" style="margin: -6px 0 12px">{{ $section['note'] }}</p> @endif

        <div class="catalog" data-infinite-merge>
            @foreach ($section['services'] as $service)
                @php
                    // A VIP customer sees the VIP price (with the normal price struck through when it differs).
                    $unit = $service->priceFor(auth()->user());
                    $vipDeal = auth()->user()?->is_vip && to_cents($unit) !== to_cents($service->price);
                    $affordable = to_cents($balance) >= to_cents($unit);
                    $blocked = $servicesSuspended || in_array($service->id, $blockedIds, true);
                @endphp
                <a @class(['service-card', 'is-blocked' => $blocked]) href="{{ route('customer.services.show', $service->id) }}">
                    <h3>
                        {{ $service->name }}
                        @if ($service->isNew()) <span class="new-tag">{{ __('New') }}</span> @endif
                        @if (!$section['star'] && in_array($service->id, $favoriteIds, true)) <span class="fav-star" title="{{ __('Frequently used') }}">@include('partials.icon', ['name' => 'star'])</span> @endif
                    </h3>
                    <p class="svc-cat" dir="auto">{{ $catalog->firstWhere('id', $service->service_category_id)?->name ?? $section['title'] }}</p>
                    <div class="svc-tags">
                        <span class="tag-price num">{{ price($unit) }}</span>
                        @if ($vipDeal) <span class="tag-vip-price">{{ __('VIP') }}</span> <s class="muted vip-was num">{{ price($service->price) }}</s> @endif
                        @if ($service->is_instant) <span class="tag-instant">{{ __('Instant') }}</span> @endif
                        @if ($service->delivery_time && !$service->is_instant) <span class="tag">{{ $service->delivery_time }}</span> @endif
                    </div>
                    <p>{{ plain_text($service->description, 110) }}</p>
                    <div class="price">
                        @if ($blocked)
                            <span class="neg">{{ __('Not available') }}</span>
                        @else
                            <span class="{{ $affordable ? 'muted' : 'neg' }}">{{ $affordable ? __('Order') : __('Not enough credit') }}</span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
        </section>
    @endforeach
    </div>

    @include('partials.load-more', ['paginator' => $paginator])
    </div>
@endsection
