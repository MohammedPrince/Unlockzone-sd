@extends('layouts.panel')

@section('title', __('Services'))
@section('eyebrow', __('Catalog'))
@section('page-title', __('Services'))
@section('page-description', __('Choose a service to see what it needs and place an order. Prices are in dollars.'))

@section('content')
    <div class="card">
        <form class="filters" method="get" action="{{ route('customer.services.index') }}" style="border-bottom: 0">
            <div class="field grow">
                <label for="q">{{ __('Search services') }}</label>
                <input type="search" id="q" name="q" value="{{ $q }}" placeholder="{{ __('Service name') }}">
            </div>
            <div class="btns">
                <button class="btn btn-primary" type="submit">{{ __('Search') }}</button>
                @if ($q) <a class="btn" href="{{ route('customer.services.index') }}">{{ __('Clear') }}</a> @endif
            </div>
        </form>
    </div>

    @if ($catalog->isEmpty())
        <div class="card" style="margin-top: 18px">
            <div class="empty"><strong>{{ $q ? __('No services match your search') : __('No services available yet') }}</strong>{{ $q ? __('Try a different name.') : __('Please check back soon.') }}</div>
        </div>
    @endif

    @php
        // Favourites first, then every category.
        $sections = $catalog->map(fn ($category) => ['title' => $category->name, 'note' => $category->description, 'services' => $category->services, 'star' => false]);
        if ($favorites->isNotEmpty()) {
            $sections = $sections->prepend(['title' => __('Frequently used'), 'note' => null, 'services' => $favorites, 'star' => true]);
        }
    @endphp

    @foreach ($sections as $section)
        <div class="section-title">
            <h2>@if ($section['star'])<span class="fav-star">@include('partials.icon', ['name' => 'star'])</span> @endif{{ $section['title'] }}</h2>
            <span class="muted">{{ trans_choice('{1} :count service|[2,*] :count services', $section['services']->count(), ['count' => $section['services']->count()]) }}</span>
        </div>
        @if ($section['note']) <p class="muted" style="margin: -6px 0 12px">{{ $section['note'] }}</p> @endif

        <div class="catalog">
            @foreach ($section['services'] as $service)
                @php
                    $affordable = to_cents($balance) >= to_cents($service->price);
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
                        <span class="tag-price num">{{ price($service->price) }}</span>
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
    @endforeach
@endsection
