@extends('layouts.public')

{{-- One service for visitors and search engines. Ordering happens in the customer area (sign-in comes back to the order form). --}}
@section('title', $name)
@section('main-class', 'pub-main')

@section('content')
    @include('partials.public-crumbs', ['crumbs' => [
        [__('Services'), \App\Support\Seo::url('public.catalog')],
        [$category, \App\Support\Seo::categoryUrl($service->category)],
    ]])

    <div class="pub-service">
        <div class="stack">
            <section class="card">
                <div class="card-body">
                    <dl class="pub-facts">
                        @if (\App\Support\Seo::showPrices())
                            <div><dt>{{ __('Price') }}</dt><dd class="num pub-price">{{ price($service->price) }}</dd></div>
                        @endif
                        @if ($delivery !== '')
                            <div><dt>{{ __('Delivery time') }}</dt><dd dir="auto">{{ $delivery }} @if ($service->is_instant) <span class="tag-instant">{{ __('Instant') }}</span> @endif</dd></div>
                        @elseif ($service->is_instant)
                            <div><dt>{{ __('Delivery time') }}</dt><dd><span class="tag-instant">{{ __('Instant') }}</span></dd></div>
                        @endif
                        <div><dt>{{ __('Category') }}</dt><dd><a href="{{ \App\Support\Seo::categoryUrl($service->category) }}" dir="auto">{{ $category }}</a></dd></div>
                        @if ($fields->isNotEmpty())
                            <div><dt>{{ __('What you need to order') }}</dt><dd>{{ $fields->map(fn ($f) => __($f->label))->implode(app()->getLocale() === 'ar' ? '، ' : ', ') }}</dd></div>
                        @endif
                    </dl>
                    <div class="pub-cta">
                        <a class="btn btn-primary btn-lg" rel="nofollow" href="{{ route('customer.services.show', $service->id) }}">@include('partials.icon', ['name' => 'cart']) {{ __('Order now') }}</a>
                        @if (auth()->guest() && setting('allow_registration') === '1')
                            <a class="btn btn-lg" href="{{ route('register') }}">@include('partials.icon', ['name' => 'user']) {{ __('Create an account') }}</a>
                        @endif
                    </div>
                </div>
            </section>

            @if ($description !== '')
                <section class="card">
                    <div class="card-head"><h2>{{ __('About this service') }}</h2></div>
                    <div class="card-body"><div class="rich rich-scroll" dir="auto">{!! $description !!}</div></div>
                </section>
            @endif
        </div>

        <section class="card pub-steps">
            <div class="card-head"><h2>{{ __('How to order') }}</h2></div>
            <div class="card-body">@include('partials.public-steps')</div>
        </section>
    </div>

    @if ($related->isNotEmpty())
        <section class="card pub-group">
            <div class="card-head"><h2>{{ __('Similar services') }}</h2></div>
            @include('partials.public-services', ['list' => $related])
        </section>
    @endif
@endsection
