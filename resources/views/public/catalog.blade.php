@extends('layouts.public')

{{-- All service categories, for visitors and search engines. --}}
@section('title', __('Unlocking services'))
@section('main-class', 'pub-main')

@section('content')
    @include('partials.public-crumbs', ['crumbs' => []])

    <p class="pub-intro" dir="auto">{{ \App\Support\Seo::text('home_tagline') }}</p>

    @if ($categories->isEmpty())
        <p class="muted">{{ __('Services will be listed here soon.') }}</p>
    @else
        <div class="home-cats">
            @foreach ($categories as $item)
                <a class="home-cat" href="{{ \App\Support\Seo::categoryUrl($item) }}">
                    <h2 dir="auto">{{ \App\Support\Seo::name($item->name) }}</h2>
                    @if ($item->description) <p dir="auto">{{ \App\Support\Seo::name(plain_text($item->description, 160)) }}</p> @endif
                    <div class="home-cat-foot">
                        <span>{{ trans_choice('{1} :count service|[2,*] :count services', $item->services_count, ['count' => $item->services_count]) }}</span>
                        @if (\App\Support\Seo::showPrices() && $item->services_min_price !== null)
                            <b class="num">{{ __('From :price', ['price' => price($item->services_min_price)]) }}</b>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif
@endsection
