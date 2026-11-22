@extends('layouts.public')

{{-- The services of one category, for visitors and search engines: 60 on a page, under the same headings as the customer catalog. --}}
@section('title', $name)
@section('main-class', 'pub-main')

@section('content')
    @include('partials.public-crumbs', ['crumbs' => [[__('Services'), \App\Support\Seo::url('public.catalog')]]])

    @if ($category->description && $services->onFirstPage())
        <p class="pub-intro" dir="auto">{{ \App\Support\Seo::name(plain_text($category->description, 600)) }}</p>
    @endif

    <form class="pub-search" method="get" action="{{ \App\Support\Seo::categoryUrl($category) }}" role="search">
        <label for="pub-q" class="sr-only">{{ __('Search') }}</label>
        <input type="search" id="pub-q" name="q" value="{{ $q }}" maxlength="80" placeholder="{{ __('Search') }}" dir="auto">
        <button type="submit" class="btn btn-primary">{{ __('Search') }}</button>
    </form>

    @if ($services->isEmpty())
        <p class="muted">{{ $q !== '' ? __('No service matches your search.') : __('Services will be listed here soon.') }}</p>
    @endif

    @foreach ($groups as $group => $list)
        <section class="card pub-group">
            {{-- A category without groups would only repeat the page title. --}}
            @if ($groups->count() > 1 || (string) $group !== $category->name)
                <div class="card-head"><h2 dir="auto">{{ \App\Support\Seo::name((string) $group) ?: $name }}</h2></div>
            @endif
            @include('partials.public-services', ['list' => $list])
        </section>
    @endforeach

    @if ($services->hasPages())
        <div class="card">{{ $services->links() }}</div>
    @endif
@endsection
